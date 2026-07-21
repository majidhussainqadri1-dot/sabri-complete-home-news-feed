# Mandatory Second One-Hour QA Policy

Project: Sabri Complete Home and News Feed

Applies from: Phase 4A onward and retrospectively whenever an earlier completed deliverable is changed.

## Standing instruction

> سب سے قبل اسے مکمل چیک کریں۔ اور یہ والے تمام ٹیسٹ کریں۔ ایک گھنٹہ لگائیں۔
>
> جب کوئی فائل، ماڈیول، مرحلہ یا package مکمل بن کر اپنے پہلے tests سے گزر جائے تو اس کے بعد ایک الگ **دوسرا ٹیسٹ** لازم ہوگا۔ یہ دوسرا test کم از کم ایک مکمل گھنٹے کا ہوگا۔ خرابی ملے تو صرف رپورٹ نہیں کی جائے گی؛ اسی branch پر root cause درست کیا جائے گا، affected tests اور documents بھی درست کیے جائیں گے، اور مکمل second QA نئے exact commit پر صفر سے دوبارہ شروع ہوگا۔

## Mandatory sequence

1. Deliverable implementation completes.
2. Normal build, contract, behavior, integration, static, package, and first acceptance tests complete.
3. A separate second independent QA begins on one exact 40-character commit SHA.
4. The second QA runs for at least 3,600 uninterrupted seconds; project workflows should use at least 3,900 seconds where practical.
5. Tests repeat throughout the duration rather than sleeping for an hour after one test pass.
6. A complete final verification runs after the minimum duration.
7. Passed evidence is retained as an immutable GitHub Actions artifact.

The first test, build success, package success, planning QA, or an earlier phase soak test does not replace this mandatory second QA.

## Defect-and-correction rule

A discovered defect must be handled as follows:

1. stop acceptance of the affected commit;
2. inspect the failing step and evidence;
3. correct the root cause on the development branch only;
4. update every affected contract, implementation file, test, package check, and explanatory document;
5. preserve or strengthen the strictest security, privacy, accessibility, rollback, and compatibility requirement;
6. commit the correction;
7. restart the entire second one-hour QA from zero on the corrected exact commit.

Elapsed time from failed, cancelled, superseded, or different-commit attempts cannot be combined.

## Required test classes

The second QA must cover all test classes applicable to the deliverable, including:

- plan and frozen-contract compliance;
- PHP, JavaScript, CSS, JSON, YAML, and shell syntax where present;
- unit and behavior tests;
- permission, authentication, IDOR, nonce, privacy, and fail-closed tests;
- activation, deactivation, upgrade, duplicate-copy, Safe Boot, Emergency Disable, snapshot, and rollback tests;
- previous-phase regression tests;
- package creation, package structure, checksum, and packaged-runtime tests;
- real WordPress or WordPress Playground integration where available;
- repeated exact-commit and tracked-file integrity checks;
- final post-duration verification.

A test category may be marked not applicable only with an explicit evidence-based reason in the checkpoint protocol.

## Exact-commit and integrity rule

Acceptance belongs only to the exact commit that completed the full second QA. The workflow must record:

- branch name;
- exact 40-character commit SHA;
- start and finish UTC timestamps;
- elapsed seconds;
- cycle count;
- initial and final tracked-file SHA-256 manifest;
- test logs;
- package checksum where a package is built;
- passed or failure evidence artifact.

Any tracked-file or commit change invalidates the running acceptance and requires restart.

## Infrastructure-failure rule

A GitHub runner failure, quota failure, cancelled job, missing runner steps, network outage, or third-party service failure is neither a code pass nor a code failure. It must be retried on the same exact commit when safe. No test may be weakened merely to obtain a green result.

## Release boundary

Until the mandatory second QA passes:

- the deliverable is not second-QA accepted;
- it is not merge-approved merely because other checks passed;
- it is not version-promoted;
- it is not live-ready;
- it is not authorized for live deployment.

Hostinger staging acceptance, backup restoration, rollback restoration, merge approval, version promotion, and live deployment remain separate explicit decisions even after the second QA passes.

## Permanent application

Every future completed file, module, phase, package, or major correction in this repository must apply this policy without requiring the user to repeat the instruction.
