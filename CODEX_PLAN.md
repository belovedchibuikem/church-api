# Family House Connect Backend Delivery Plan

Updated: 2026-08-25

## Current state

Phase 0 discovery is complete and Phase 1 foundation is operationally implemented pending hosted/production infrastructure verification. Laravel 13.27.0 boots on PHP 8.3, versioned API routing is registered, and the public/user/admin route surfaces are separated. Liveness, dependency readiness, normalized error envelopes, correlation identifiers, configurable public throttling, privacy-safe structured JSON logging, queue retention schedules, worker/scheduler deployment templates, and a MySQL/Redis CI gate are implemented. MySQL 8.4 is the active local runtime database with InnoDB tables, and a local isolated backup/restore drill has passed. Local file storage remains the default, while an encrypted, validation-gated S3-compatible storage core is ready for a future secured administration surface. Phase 2A has started with the canonical Person/Profile root, one-to-one User linkage, opaque ULIDs, and an append-only audit writer.

## Delivery sequence

1. **Foundation — implementation complete; environment verification pending:** structured logging, Redis-ready cache/queue configuration, supervised workers, protected scheduler maintenance, MySQL/Redis CI, monitoring/backup runbooks, and a successful local MySQL restore drill are present. The hosted CI run and production Redis/MySQL/backup/observability environments remain OD-012 gates.
2. **Identity and access — foundation in progress; transport pending decision:** canonical Person/Profile and User linkage plus audit recording are implemented. Browser/mobile authentication, verification, MFA, sessions/devices, permissions, scopes, and consent remain gated by OD-001 through OD-005.
3. **Geography and organization:** configurable hierarchy, locations, scope containment, churches, and Home Churches.
4. **Shared platform services:** audit, media/files, search, notifications, configuration, and feature flags.
5. **Church and pastoral care.**
6. **Mission.**
7. **KCA.**
8. **Press.**
9. **Events and finance.**
10. **Communications.**
11. **Reporting, analytics, and advisory AI.**
12. **Safeguarding, privacy, and production hardening.**
13. **Generated TypeScript/Dart clients and cross-client E2E integration.**

Each phase must deliver domain rules, authorization, API contracts, audit behavior, tests, and traceability. A later phase must not be marked complete because routes or mock responses exist.
