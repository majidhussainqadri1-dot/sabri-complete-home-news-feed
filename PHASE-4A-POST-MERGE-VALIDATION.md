# Phase 4A Mandatory QA and Post-Merge Validation Recovery

Exact Phase 4A head: `808a7a85a3f5aae4ed6566725b097d28dbcfd562`

Exact merge commit: `0bf78d651b908e4d0441ffb19c1da1a5b3a8bbb2`

The original mandatory second one-hour workflow was feature-branch push-only and did not leave a verified passed artifact before PR #3 merged. This corrective QA branch adds an observable pull-request workflow that tests the immutable historical Phase 4A head and the exact merge commit without modifying `main`.

Acceptance requires:

- source WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1 matrices on the exact Phase 4A head;
- uninterrupted minimum 3,900 seconds and exactly 13 cycles;
- exact-head initial/final tracked manifests;
- full package checksum and ZIP structure verification;
- packaged WordPress matrices on both environments;
- exact merge-commit regressions, static checks, package rebuild, checksum, and manifest;
- retained mandatory, merge, and combined acceptance artifacts.

No version promotion, public-gate activation, automatic publication, staging activation, or live deployment is authorized.
