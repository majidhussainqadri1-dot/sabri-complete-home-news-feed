# Phase 5 Implementation Safety Boundaries

- Work only on `build/phase-5-final-completion-1.2.0` and its Draft PR.
- Never write directly to `main`.
- Never merge, enable auto-merge, mark Ready, promote versions, enable public gates, activate Hostinger staging, or deploy live without explicit owner action.
- Preserve plugin/schema `1.0.0` and checkpoint `4A` until the final dedicated release-candidate promotion commit after all preceding acceptance gates pass.
- All new gates default to disabled.
- Automatic publication remains prohibited.
- Migrations are additive, idempotent, resumable, auditable, non-destructive, and rollback-aware.
- Public serializers are inclusion-based and fail closed.
- Private editorial, source, reviewer, submitter, patient, preview, account, token, security, and operational data never enters public output or shared caches.
- Any code/test/workflow correction creates a new exact head and restarts final two-hour acceptance from zero.
- The user is never asked to run workflows, inspect logs, capture screenshots, or diagnose failures.
