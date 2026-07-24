#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/phase4a-second-one-hour-qa"
MIN_SECONDS=3900
CYCLES=13
INTERVAL_SECONDS=300
STATUS="failed"
START_EPOCH=0
HEAD_SHA=""
MANIFEST_SHA=""

rm -rf "$OUT"
mkdir -p "$OUT/cycles" "$OUT/final" "$OUT/package"

on_exit() {
  local rc=$?
  local finish_epoch elapsed
  finish_epoch="$(date +%s)"
  elapsed=0
  if [[ "$START_EPOCH" -gt 0 ]]; then elapsed=$((finish_epoch - START_EPOCH)); fi
  {
    echo "status=$STATUS"
    echo "exit_code=$rc"
    echo "head_sha=$HEAD_SHA"
    echo "start_epoch=$START_EPOCH"
    echo "finish_epoch=$finish_epoch"
    echo "duration_seconds=$elapsed"
    echo "cycles_required=$CYCLES"
    echo "minimum_seconds=$MIN_SECONDS"
    echo "tracked_manifest_sha256=$MANIFEST_SHA"
  } > "$OUT/result.env"
}
trap on_exit EXIT

cd "$ROOT"
HEAD_SHA="$(git rev-parse HEAD)"
printf '%s\n' "$HEAD_SHA" > "$OUT/exact-commit.txt"
printf '%s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" > "$OUT/workflow-started-utc.txt"

critical_files=(
  "MANDATORY-SECOND-QA-POLICY.md"
  "PHASE-4A-SECOND-QA-PROTOCOL.md"
  "PHASE-4-CONTRACTS.md"
  "PHASE-4-CONTRACTS-ADDENDUM-1.md"
  "PHASE-4-CONTRACTS-ADDENDUM-2-WORDPRESS-STATUS-STORAGE.md"
  "PHASE-4-CONTRACTS-ADDENDUM-3-SECURITY-HARDENING.md"
  "PHASE-4-ARCHITECTURE.md"
  "PHASE-4-SECURITY-PRIVACY.md"
  "PHASE-4-EDITORIAL-POLICY.md"
  "includes/class-phase4-contracts.php"
  "includes/class-news-feature-settings.php"
  "includes/class-editorial-news-post-type.php"
  "includes/class-news-taxonomies.php"
  "includes/class-news-statuses.php"
  "includes/class-news-capabilities.php"
  "includes/class-plugin.php"
  "includes/class-activator.php"
  "includes/class-snapshot.php"
  "includes/class-rollback.php"
  "tests/run-phase4a-content-model-tests.php"
  "tests/run-phase4a-security-contract-tests.php"
  "tests/run-phase4a-rollback-edge-tests.php"
  "tests/run-phase4a-playground-tests.mjs"
  "tests/run-phase4a-second-one-hour-qa.sh"
  ".github/workflows/phase4a-content-model-tests.yml"
  ".github/workflows/phase4a-second-one-hour-qa.yml"
)
for file in "${critical_files[@]}"; do
  [[ -f "$file" ]] || { echo "Missing required Phase 4A second-QA file: $file" >&2; exit 1; }
done

write_tracked_manifest() {
  local destination="$1"
  git ls-files -z | LC_ALL=C sort -z | xargs -0 sha256sum > "$destination"
}

verify_exact_commit_and_manifest() {
  local label="$1" current_sha current_manifest
  current_sha="$(git rev-parse HEAD)"
  [[ "$current_sha" == "$HEAD_SHA" ]] || { echo "$label: exact commit changed from $HEAD_SHA to $current_sha" >&2; exit 1; }
  current_manifest="$OUT/${label}-tracked-files.sha256"
  write_tracked_manifest "$current_manifest"
  if ! cmp -s "$OUT/initial-tracked-files.sha256" "$current_manifest"; then
    echo "$label: tracked-file SHA-256 manifest changed during the run" >&2
    diff -u "$OUT/initial-tracked-files.sha256" "$current_manifest" || true
    exit 1
  fi
  [[ -z "$(git status --porcelain --untracked-files=no)" ]] || { echo "$label: tracked working tree is not clean" >&2; git status --short >&2; exit 1; }
}

run_phase3_matrix() {
  php tests/run-phase3-contract-tests.php
  php tests/run-phase3-infrastructure-tests.php
  php tests/run-phase3b-reactions-saves-tests.php
  php tests/run-phase3b-race-shortcode-tests.php
  php tests/run-phase3c-comments-tests.php
  php tests/run-phase3d-follows-tests.php
  php tests/run-phase3e-reports-tests.php
  php tests/run-phase3f-polls-tests.php
  php tests/run-phase3g-notifications-views-tests.php
  php tests/run-phase3h-hardening-tests.php
  php tests/run-phase3-social-features-share-tests.php
  php tests/run-public-query-routing-tests.php
  php tests/run-wordpress-option-filter-compatibility-tests.php
  php tests/run-duplicate-plugin-compat-tests.php
}

run_core_suite() {
  php tests/run-phase4-contract-tests.php
  php tests/run-phase4a-security-contract-tests.php
  php tests/run-phase4-document-audit.php
  php tests/run-phase4a-content-model-tests.php
  php tools/run-tests.php
  run_phase3_matrix
}

run_full_php_lint() {
  find . -name '*.php' \
    -not -path './release/*' -not -path './vendor/*' -not -path './node_modules/*' \
    -not -path './phase4a-second-one-hour-qa/*' -print0 | xargs -0 -n1 php -l
}

run_critical_php_lint() {
  local file
  for file in "${critical_files[@]}"; do
    if [[ "$file" == *.php ]]; then
      php -l "$file"
    fi
  done
}

run_js_syntax() {
  find assets/js tests \( -name '*.js' -o -name '*.mjs' \) -type f -print0 | xargs -0 -n1 node --check
}

write_tracked_manifest "$OUT/initial-tracked-files.sha256"
MANIFEST_SHA="$(sha256sum "$OUT/initial-tracked-files.sha256" | awk '{print $1}')"
printf '%s\n' "$MANIFEST_SHA" > "$OUT/tracked-manifest-digest.sha256"

cat > "$OUT/defect-correction-record.md" <<EOF
# Phase 4A Second QA Defect and Correction Record

Exact run commit: \`$HEAD_SHA\`

This workflow never edits the repository while testing. A discovered defect rejects the commit. The defect must be corrected in a new commit and this complete second QA restarts from zero. Correction history is retained in PR #3 and Git history.
EOF

{
  echo "Initial complete verification"
  echo "Exact commit: $HEAD_SHA"
  echo "Started UTC: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  run_full_php_lint
  run_js_syntax
  run_core_suite
  pwsh -NoProfile -File tools/run-local-static-checks.ps1 -SkipPhpLint -SkipPhpTests
  verify_exact_commit_and_manifest "initial"
} 2>&1 | tee "$OUT/initial-complete-verification.log"

START_EPOCH="$(date +%s)"
printf '%s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" > "$OUT/soak-started-utc.txt"

for cycle in $(seq 1 "$CYCLES"); do
  cycle_log="$OUT/cycles/cycle-$(printf '%02d' "$cycle").log"
  cycle_started="$(date +%s)"
  {
    echo "Cycle $cycle of $CYCLES"
    echo "Exact commit: $HEAD_SHA"
    echo "Cycle started UTC: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    run_critical_php_lint
    run_core_suite
    verify_exact_commit_and_manifest "cycle-$(printf '%02d' "$cycle")"
    echo "Cycle completed UTC: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  } 2>&1 | tee "$cycle_log"

  target_epoch=$((START_EPOCH + cycle * INTERVAL_SECONDS))
  now_epoch="$(date +%s)"
  [[ "$now_epoch" -ge "$target_epoch" ]] || sleep $((target_epoch - now_epoch))
  printf 'cycle=%s started_epoch=%s completed_epoch=%s target_epoch=%s\n' "$cycle" "$cycle_started" "$(date +%s)" "$target_epoch" >> "$OUT/cycle-timing.log"
done

minimum_finish=$((START_EPOCH + MIN_SECONDS))
now_epoch="$(date +%s)"
[[ "$now_epoch" -ge "$minimum_finish" ]] || sleep $((minimum_finish - now_epoch))

{
  echo "Final post-duration verification"
  echo "Exact commit: $HEAD_SHA"
  echo "Final verification started UTC: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  run_full_php_lint
  run_js_syntax
  run_core_suite
  pwsh -NoProfile -File tools/run-local-static-checks.ps1 -SkipPhpLint -SkipPhpTests

  rm -rf release
  pwsh -NoProfile -File tools/build-release.ps1
  mapfile -t package_zips < <(find release -maxdepth 1 -type f -name '*-PHASE-3-STAGING-CANDIDATE.zip' | LC_ALL=C sort)
  mapfile -t package_shas < <(find release -maxdepth 1 -type f -name '*-PHASE-3-STAGING-CANDIDATE.sha256' | LC_ALL=C sort)
  [[ "${#package_zips[@]}" -eq 1 && "${#package_shas[@]}" -eq 1 ]] || { echo "Expected exactly one staging package and checksum" >&2; exit 1; }
  package_zip="${package_zips[0]}"
  package_sha="${package_shas[0]}"

  expected_sha="$(awk '{print tolower($1)}' "$package_sha")"
  actual_sha="$(sha256sum "$package_zip" | awk '{print $1}')"
  [[ "$expected_sha" == "$actual_sha" ]] || { echo "Package SHA-256 mismatch" >&2; exit 1; }

  unzip -Z1 "$package_zip" > "$OUT/package/package-entries.txt"
  required_package_entries=(
    "sabri-complete-home-news-feed/sabri-complete-home-news-feed.php"
    "sabri-complete-home-news-feed/includes/class-phase4-contracts.php"
    "sabri-complete-home-news-feed/includes/class-news-feature-settings.php"
    "sabri-complete-home-news-feed/includes/class-editorial-news-post-type.php"
    "sabri-complete-home-news-feed/includes/class-news-taxonomies.php"
    "sabri-complete-home-news-feed/includes/class-news-statuses.php"
    "sabri-complete-home-news-feed/includes/class-news-capabilities.php"
    "sabri-complete-home-news-feed/includes/class-snapshot.php"
    "sabri-complete-home-news-feed/includes/class-rollback.php"
  )
  for entry in "${required_package_entries[@]}"; do
    grep -Fxq "$entry" "$OUT/package/package-entries.txt" || { echo "Missing Phase 4A package entry: $entry" >&2; exit 1; }
  done

  cp "$package_zip" "$OUT/package/"
  cp "$package_sha" "$OUT/package/"
  printf '%s  %s\n' "$actual_sha" "$(basename "$package_zip")" > "$OUT/package/verified-package.sha256"
  verify_exact_commit_and_manifest "final"
  echo "Final verification completed UTC: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
} 2>&1 | tee "$OUT/final/final-complete-verification.log"

finish_epoch="$(date +%s)"
duration=$((finish_epoch - START_EPOCH))
[[ "$duration" -ge "$MIN_SECONDS" ]] || { echo "Second QA duration was only $duration seconds; required $MIN_SECONDS" >&2; exit 1; }

STATUS="passed"
printf '%s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" > "$OUT/workflow-finished-utc.txt"
echo "OK - Phase 4A mandatory second QA passed on $HEAD_SHA after $duration seconds and $CYCLES cycles."
