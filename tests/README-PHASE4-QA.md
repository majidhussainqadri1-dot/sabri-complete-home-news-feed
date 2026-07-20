# Phase 4 QA Test Entry Points

Run from the repository root:

```bash
php tests/run-phase4-contract-tests.php
php tests/run-phase4-document-audit.php
bash tests/run-phase4-one-hour-document-qa.sh
```

The first command validates frozen identifiers and cross-document requirements.

The second command validates Markdown integrity, checklist uniqueness, document hashes, and consistency boundaries.

The third command performs the complete repeated QA for at least 3,900 seconds and produces evidence under `phase4-one-hour-document-qa/`.

The long-running command is intended for CI or a dedicated test environment. It validates planning documents and existing repository regressions; it does not claim Phase 4 feature implementation or production acceptance.
