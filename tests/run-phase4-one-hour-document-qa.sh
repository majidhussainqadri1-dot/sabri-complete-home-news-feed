#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TARGET_SECONDS=3900
TOTAL_CYCLES=13
OBSERVATION_SECONDS=300
START_EPOCH="$(date +%s)"
TESTED_COMMIT="$(git rev-parse HEAD)"
LOG_DIR="phase4-one-hour-document-qa"
AUDIT_OUTPUT_DIR="phase4-document-audit"

DOCUMENTS=(
  PHASE-4-CONTRACTS.md
  PHASE-4-CONTRACTS-ADDENDUM-1.md
  PHASE-4-EDITORIAL-POLICY.md
  PHASE-4-ARCHITECTURE.md
  PHASE-4-SECURITY-PRIVACY.md
  PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md
  PHASE-4-ROLLBACK-RUNBOOK.md
  PHASE-4-COMPLETENESS-AUDIT.md
)

rm -rf "$LOG_DIR" "$AUDIT_OUTPUT_DIR"
mkdir -p "$LOG_DIR"

log() {
  printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" | tee -a "$LOG_DIR/summary.log"
}

verify_repository_identity() {
  local current_commit
  current_commit="$(git rev-parse HEAD)"
  test "$current_commit" = "$TESTED_COMMIT"
  test -z "$(git status --porcelain --untracked-files=no)"
}

verify_document_manifest() {
  sha256sum -c "$LOG_DIR/initial-document-manifest.sha256"
}

run_cycle() {
  local cycle="$1"
  log "Cycle ${cycle}/${TOTAL_CYCLES} started."

  rm -rf "$AUDIT_OUTPUT_DIR"

  {
    php tests/run-phase4-contract-tests.php
    php tests/run-phase4-document-audit.php
    php tools/run-tests.php
  } 2>&1 | tee "$LOG_DIR/cycle-${cycle}-tests.log"

  find . -name '*.php' \
    -not -path './release/*' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -not -path './phase4-document-audit/*' \
    -print0 | xargs -0 -n1 php -l > "$LOG_DIR/cycle-${cycle}-php-lint.log"

  verify_document_manifest > "$LOG_DIR/cycle-${cycle}-manifest-check.log"
  verify_repository_identity

  if [ -f "$AUDIT_OUTPUT_DIR/manifest.json" ]; then
    cp "$AUDIT_OUTPUT_DIR/manifest.json" "$LOG_DIR/cycle-${cycle}-document-manifest.json"
  fi

  log "Cycle ${cycle}/${TOTAL_CYCLES} passed."
}

for document in "${DOCUMENTS[@]}"; do
  test -f "$document"
done
sha256sum "${DOCUMENTS[@]}" > "$LOG_DIR/initial-document-manifest.sha256"

log "Phase 4 repeated planning QA started. Required minimum duration: ${TARGET_SECONDS} seconds."
log "Commit under test: ${TESTED_COMMIT}."
log "Planned cycles: ${TOTAL_CYCLES}; observation interval: ${OBSERVATION_SECONDS} seconds."

for cycle in $(seq 1 "$TOTAL_CYCLES"); do
  run_cycle "$cycle"
  if (( cycle < TOTAL_CYCLES )); then
    log "Observation interval after cycle ${cycle}: ${OBSERVATION_SECONDS} seconds."
    sleep "$OBSERVATION_SECONDS"
  fi
done

ELAPSED="$(( $(date +%s) - START_EPOCH ))"
if (( ELAPSED < TARGET_SECONDS )); then
  REMAINING="$(( TARGET_SECONDS - ELAPSED ))"
  log "All repeated cycles passed early; preserving the unchanged tested commit for ${REMAINING} additional seconds."
  sleep "$REMAINING"
fi

log "Running final post-soak verification."
rm -rf "$AUDIT_OUTPUT_DIR"
{
  php tests/run-phase4-contract-tests.php
  php tests/run-phase4-document-audit.php
  php tools/run-tests.php
} 2>&1 | tee "$LOG_DIR/final-tests.log"
verify_document_manifest | tee "$LOG_DIR/final-manifest-check.log"
verify_repository_identity

END_EPOCH="$(date +%s)"
DURATION_SECONDS="$(( END_EPOCH - START_EPOCH ))"
if (( DURATION_SECONDS < TARGET_SECONDS )); then
  log "FAIL: recorded duration ${DURATION_SECONDS}s is below ${TARGET_SECONDS}s."
  exit 1
fi

MANIFEST_SHA256="$(sha256sum "$LOG_DIR/initial-document-manifest.sha256" | awk '{print $1}')"

cat > "$LOG_DIR/result.env" <<EOF
status=passed
start_epoch=${START_EPOCH}
end_epoch=${END_EPOCH}
duration_seconds=${DURATION_SECONDS}
cycles=${TOTAL_CYCLES}
final_commit=${TESTED_COMMIT}
document_manifest_sha256=${MANIFEST_SHA256}
EOF

cat > "$LOG_DIR/FINAL-REPORT.md" <<EOF
# Phase 4 Repeated Planning QA — Passed

- Status: passed
- Exact commit: \`${TESTED_COMMIT}\`
- Duration: ${DURATION_SECONDS} seconds
- Full cycles: ${TOTAL_CYCLES}
- Observation interval: ${OBSERVATION_SECONDS} seconds
- Documents checked: ${#DOCUMENTS[@]}
- Initial manifest SHA-256: \`${MANIFEST_SHA256}\`
- Phase 4 contract tests: passed in every cycle and final verification
- Markdown integrity audit: passed in every cycle and final verification
- Existing repository regression suite: passed in every cycle and final verification
- PHP syntax: passed in every cycle
- Tracked working tree: unchanged throughout the soak

This evidence validates planning and contract consistency only. It does not represent Phase 4 feature implementation, Hostinger staging acceptance, version promotion, merge approval, or live deployment.
EOF

log "PASS: Phase 4 repeated planning QA completed in ${DURATION_SECONDS} seconds with ${TOTAL_CYCLES} full cycles and final verification."
