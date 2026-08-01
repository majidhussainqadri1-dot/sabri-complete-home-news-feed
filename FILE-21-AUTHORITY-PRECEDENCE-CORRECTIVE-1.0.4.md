# File 21 — Authority-Precedence Corrective Release 1.0.4

## Governing defect

A legacy institutional or professional account may carry more than one WordPress role. In particular, a Founder, Administrator, verified Doctor, or unverified Doctor may also retain an older `subscriber`, `patient`, or `sabri_patient` role from an earlier membership workflow.

File 21 previously evaluated Student/Patient/Subscriber denial before evaluating the higher-authority Founder, Administrator, Doctor, or plugin-owned capability grant. The result was a false denial on `/create/` even when File 00, File 22, the File 21 adapter, and `sabri_feed_create_posts` were otherwise healthy.

## Corrective rule

Authorization now uses explicit authority precedence:

1. Safe Mode and Emergency Disable remain absolute gates.
2. A current-actor `sabri_feed_create_posts` or `manage_options` grant authorizes creation.
3. Canonical Founder, Administrator, verified Doctor, and unverified Doctor identities authorize creation according to their existing publication/review policy.
4. Student-only, Patient-only, Subscriber-only, unknown, and Editorial-only accounts remain denied.
5. A lower-authority legacy role cannot cancel a higher-authority institutional or professional role.

## Publication boundaries

- Founder and Administrator retain immediate publication authority even when a lower-authority legacy role is present.
- Verified and unverified Doctors retain the configured publish-or-review path even when a lower-authority legacy role is present.
- No Student, Patient, or Subscriber-only account gains creation authority.
- No `manage_options` bypass is added to File 22; the native File 21 capability contract remains intact.

## Regression evidence

`tests/run-composer-authority-precedence-tests.php` covers:

- Administrator plus Subscriber;
- Founder plus Patient;
- verified Doctor plus Subscriber;
- unverified Doctor plus Subscriber;
- Student-only denial;
- Patient-only denial;
- Subscriber-only denial;
- Emergency Disable overriding Administrator authority.

## Release identity

- Runtime: `1.0.4`
- Schema: `1.0.0`
- Canonical folder: `sabri-complete-home-news-feed`
- Canonical package base: `21-sabri-complete-home-news-feed-1.0.4-AUTHORITY-PRECEDENCE-CORRECTIVE`

## Deployment boundary

The package must be installed on staging first. WordPress must display Version `1.0.4`. After activation or replacement, one authenticated Administrator request must be made so the existing bounded capability reconciliation can run. Cache purge, `/create/` verification, mixed-role verification, rollback proof, and explicit Founder approval remain mandatory before live promotion.
