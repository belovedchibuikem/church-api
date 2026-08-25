# Requirements Traceability

Updated: 2026-08-25

Status values: `implemented`, `planned`, `blocked`, `not-started`.

| Requirement | Status | Evidence / next gate |
| --- | --- | --- |
| One authoritative Laravel backend | implemented | Single Laravel application in this repository. |
| Public, user, and admin API surfaces | implemented | `routes/api/v1/public.php`, `user.php`, and `admin.php` registered below `/api/v1`. |
| Shared domain/application layer | implemented | Transport-neutral storage and identity/audit actions live outside surface controllers and are shared by future User/Admin APIs. |
| Versioned JSON contract | implemented | Success and stable-code error envelopes, `/api/v1`, `/api/v1/health`, `/api/v1/health/readiness`, and `openapi/openapi.yaml`. |
| Correlation identifiers | implemented | `AssignCorrelationId` validates or creates UUIDs and returns `X-Correlation-ID`. |
| Liveness and readiness | implemented | Liveness is process-only; readiness safely probes database, cache, and the configured queue backend without returning connection details. |
| Public API rate limiting | implemented | Public v1 routes use the configurable `public-api` limiter and normalized `429 RATE_LIMIT_EXCEEDED` responses. |
| MySQL configuration | implemented | MySQL 8.4 is the runtime default, local migrations pass on `family_house_connect`, and the connection pins InnoDB. PHPUnit uses the separate `family_house_connect_testing` schema. |
| Optional S3-compatible storage | implemented | Local storage is the safe default. Encrypted MySQL configuration, connection probing, activation/deactivation, runtime disk resolution, and the Flysystem S3 adapter are implemented without making S3 a boot dependency. |
| Redis cache/queue | planned | Redis cache/queue configuration, readiness coverage, worker template, and a disposable Redis CI service are implemented. The hosted workflow and production Redis service are not yet verified. |
| Queue workers and scheduler | implemented | Supervisor/cron deployment templates plus protected failed-job and batch pruning schedules are present and tested. Production process supervision and heartbeat monitoring remain environment gates. |
| Structured logging | implemented | NDJSON file/stderr logging includes correlation context, recursively redacts sensitive keys, and keeps stack traces opt-in. |
| CI quality gate | implemented | GitHub Actions defines PHP 8.3, MySQL 8.4, Redis, migrations, Composer validation/audit, PHPUnit, and Pint enforcement; the hosted workflow has not yet run. |
| Backup, restore, and monitoring | implemented | MySQL backup/restore and monitoring runbooks exist; a local isolated MySQL 8.4 restore drill matched tables, engines, migrations, and representative counts. Production recovery objectives and observability remain OD-007/OD-012 gates. |
| Authentication, MFA, sessions/devices | blocked | Requires decisions OD-001 and OD-002. |
| Permission + scope + record policy authorization | planned | Model documented; no protected operation is exposed yet. |
| Canonical Person identity | implemented | `Person` is the canonical root with opaque ULID, one `PersonProfile`, optional one-to-one `User`, locking, conflict checks, and audited idempotent linkage. |
| Generic geography hierarchy | not-started | Phase 3. |
| Audit and access decisions | planned | Append-only audit event persistence and writer are implemented; access-decision records, database-level write restrictions, retention, and privileged API integration remain. |
| Church, Mission, KCA, Press, Events, Finance | not-started | Phases 5-9. |
| Safeguarding/privacy workflows | not-started | Phase 12, with earlier restricted-data foundations. |
| OpenAPI-generated TypeScript/Dart clients | blocked | Contract generation begins after Identity operations stabilize. |
| Next.js and Flutter live integration | blocked | Both clients currently use fixture/visual-review authorization adapters. |

Client requirement sources reviewed:

- `../frontend/REQUIREMENTS_TRACEABILITY.md`
- `../frontend/Family_House_Connect_NextJS_Design_Closure_Developer_Handoff.docx`
- `../mobile/REQUIREMENTS_TRACEABILITY.md`
- `../mobile/OPEN_DECISIONS.md`
