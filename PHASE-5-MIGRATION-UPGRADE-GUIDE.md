# Phase 5 Migration and Upgrade Guide

- Fresh activation creates or repairs Phase 5 tables additively with all gates disabled.
- Upgrade from accepted `1.0.0` preserves all existing Phase 2–4 data and creates only missing Phase 5 structures.
- Migration uses a time-bounded lock, records state/report options, verifies tables and indexes, and may be safely repeated.
- A failed or partial migration remains incomplete and is retried through the same idempotent installer; no destructive rollback is required.
- Deactivation clears Phase 5 cron hooks but preserves data.
- Reactivation repairs registration and schedules without enabling gates or publishing content.
- Code rollback may leave dormant Phase 5 tables in place.
