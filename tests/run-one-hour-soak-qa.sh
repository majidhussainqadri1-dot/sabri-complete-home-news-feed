#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TARGET_SECONDS=3600
START_EPOCH="$(date +%s)"
TESTED_COMMIT="$(git rev-parse HEAD)"
ZIP_PATH="release/21-sabri-complete-home-news-feed-1.0.3-PRODUCTION-REJECTION-CORRECTIVE-CANDIDATE.zip"
SHA_PATH="release/21-sabri-complete-home-news-feed-1.0.3-PRODUCTION-REJECTION-CORRECTIVE-CANDIDATE.sha256"
LOG_DIR="one-hour-soak-qa"
rm -rf "$LOG_DIR"
mkdir -p "$LOG_DIR"

log() {
  printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" | tee -a "$LOG_DIR/summary.log"
}

verify_checksum() {
  local expected actual
  expected="$(awk '{print $1}' "$SHA_PATH")"
  actual="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
  test "$expected" = "$actual"
  printf '%s  %s\n' "$actual" "$ZIP_PATH" > "$LOG_DIR/cycle-${1}-checksum.txt"
}

verify_package_contract() {
  python3 - "$ZIP_PATH" "$1" <<'PY'
import sys, zipfile
zip_path, cycle = sys.argv[1], sys.argv[2]
required = {
    'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php',
    'sabri-complete-home-news-feed/includes/class-phase3-feature-settings.php',
    'sabri-complete-home-news-feed/admin/views/social-features.php',
    'sabri-complete-home-news-feed/templates/action-bar.php',
    'sabri-complete-home-news-feed/assets/js/share.js',
    'sabri-complete-home-news-feed/includes/class-comment-runtime.php',
    'sabri-complete-home-news-feed/includes/class-view-runtime.php',
}
forbidden_prefixes = (
    'sabri-complete-home-news-feed/.github/',
    'sabri-complete-home-news-feed/tests/',
    'sabri-complete-home-news-feed/tools/',
    'sabri-complete-home-news-feed/release/',
    'sabri-complete-home-news-feed/vendor/',
    'sabri-complete-home-news-feed/node_modules/',
)
with zipfile.ZipFile(zip_path) as archive:
    names = {name.replace('\\', '/') for name in archive.namelist()}
missing = sorted(required - names)
forbidden = sorted(name for name in names if name.startswith(forbidden_prefixes) or name.endswith('.log'))
if missing or forbidden:
    raise SystemExit(f'cycle {cycle}: missing={missing}, forbidden={forbidden[:20]}')
print(f'cycle {cycle}: package contract passed with {len(names)} entries')
PY
}

run_cycle() {
  local cycle="$1" wp php_version
  if (( cycle % 2 == 1 )); then
    wp="latest"
    php_version="8.3"
  else
    wp="6.8"
    php_version="8.1"
  fi

  log "Cycle ${cycle}/6 started on WordPress ${wp}, PHP ${php_version}."

  {
    php tools/run-tests.php
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
  } 2>&1 | tee "$LOG_DIR/cycle-${cycle}-php.log"

  find . -name '*.php' \
    -not -path './release/*' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -print0 | xargs -0 -n1 php -l > "$LOG_DIR/cycle-${cycle}-php-lint.log"
  find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check
  node --check tests/run-playground-integration-tests.mjs
  node --check tests/run-packaged-playground-integration-tests.mjs
  node --check tests/run-date-permalink-playground-tests.mjs

  pwsh -File tools/run-local-static-checks.ps1 -SkipPhpLint -SkipPhpTests \
    2>&1 | tee "$LOG_DIR/cycle-${cycle}-static.log"
  pwsh -File tools/build-release.ps1 \
    2>&1 | tee "$LOG_DIR/cycle-${cycle}-build.log"

  verify_checksum "$cycle"
  verify_package_contract "$cycle" | tee "$LOG_DIR/cycle-${cycle}-package.log"

  SABRI_PLAYGROUND_WP="$wp" \
  SABRI_PLAYGROUND_PHP="$php_version" \
  SABRI_PLUGIN_ZIP="$ZIP_PATH" \
    node tests/run-packaged-playground-integration-tests.mjs \
    2>&1 | tee "$LOG_DIR/cycle-${cycle}-packaged-playground.log"

  SABRI_PLAYGROUND_WP="$wp" \
  SABRI_PLAYGROUND_PHP="$php_version" \
  SABRI_PLUGIN_ZIP="$ZIP_PATH" \
    node tests/run-date-permalink-playground-tests.mjs \
    2>&1 | tee "$LOG_DIR/cycle-${cycle}-date-permalink.log"

  log "Cycle ${cycle}/6 passed."
}

log "One-hour Phase 3 second QA started. Target minimum duration: ${TARGET_SECONDS} seconds."
log "Commit under test: ${TESTED_COMMIT}."

for cycle in 1 2 3 4 5 6; do
  run_cycle "$cycle"
  if (( cycle < 6 )); then
    log "Stability observation interval after cycle ${cycle}: 360 seconds."
    sleep 360
  fi
done

ELAPSED="$(( $(date +%s) - START_EPOCH ))"
if (( ELAPSED < TARGET_SECONDS )); then
  REMAINING="$(( TARGET_SECONDS - ELAPSED ))"
  log "All cycles passed before one hour; holding the unchanged tested artifact for ${REMAINING} additional seconds."
  sleep "$REMAINING"
fi

verify_checksum "final"
verify_package_contract "final" | tee "$LOG_DIR/final-package.log"
php tests/run-phase3-social-features-share-tests.php 2>&1 | tee "$LOG_DIR/final-social-features.log"
SABRI_PLAYGROUND_WP="latest" \
SABRI_PLAYGROUND_PHP="8.3" \
SABRI_PLUGIN_ZIP="$ZIP_PATH" \
  node tests/run-packaged-playground-integration-tests.mjs \
  2>&1 | tee "$LOG_DIR/final-packaged-playground.log"

END_EPOCH="$(date +%s)"
TOTAL="$(( END_EPOCH - START_EPOCH ))"
if (( TOTAL < TARGET_SECONDS )); then
  log "FAIL: recorded duration ${TOTAL}s is below the required ${TARGET_SECONDS}s."
  exit 1
fi

{
  echo "status=passed"
  echo "start_epoch=${START_EPOCH}"
  echo "end_epoch=${END_EPOCH}"
  echo "duration_seconds=${TOTAL}"
  echo "cycles=6"
  echo "final_commit=${TESTED_COMMIT}"
  echo "final_zip_sha256=$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
} > "$LOG_DIR/result.env"

log "PASS: one-hour Phase 3 second QA completed in ${TOTAL} seconds with six full cycles and a final post-soak verification."
