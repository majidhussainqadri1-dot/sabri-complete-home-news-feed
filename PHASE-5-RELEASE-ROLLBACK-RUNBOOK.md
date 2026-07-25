# Phase 5 Release and Rollback Runbook

No release action occurs from this document alone. Required sequence: exact-head ordinary QA; migration/security/privacy/performance artifacts; uninterrupted two-hour QA; separately authorized Hostinger staging migration and gate matrix; rollback rehearsal; dedicated version/schema/checkpoint promotion commit; rebuilt immutable release candidate; owner-authorized merge; post-merge validation; separately authorized live deployment.

Rollback bundle contains previous accepted ZIP, new candidate ZIP, SHA-256 files, runtime/tracked/schema/migration manifests, Emergency Disable instructions, and data compatibility statement. Code rollback is non-destructive.
