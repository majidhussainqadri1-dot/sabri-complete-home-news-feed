#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
find includes admin public templates tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
node --check assets/js/phase5-public.js
node --check assets/js/phase5-admin.js
node --check tests/run-phase5-playground-tests.mjs
node --check tests/run-phase5-browser-tests.mjs
php tests/run-file21-settings-recursion-tests.php
php tests/run-file21-production-rejection-tests.php
php tests/run-file21-corrective-tests.php
php tests/run-file21-completion-audit-tests.php
php tests/run-phase5-final-tests.php
php tests/run-phase5-security-privacy-tests.php
php tests/run-phase5-migration-tests.php
php tests/run-phase5-ui-completeness-tests.php
php tests/run-phase5-traceability-tests.php
printf 'Phase 5 local acceptance suite passed.\n'
