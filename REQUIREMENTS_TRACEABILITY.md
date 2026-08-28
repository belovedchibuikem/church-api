# Requirements Traceability

Updated: 2026-08-26

Status values: `implemented`, `planned`, `blocked`, `not-started`.

| Requirement | Status | Evidence / next gate |
| --- | --- | --- |
| One authoritative Laravel backend | implemented | Single Laravel application in this repository. |
| Public, user, and admin API surfaces | implemented | Fourteen public, 17 browser/mobile identity, 9 protected User, and 44 protected Admin operations are registered below `/api/v1`. |
| Shared domain/application layer | implemented | Transport-neutral identity, authorization, platform, ministry, communications, reporting, safeguarding, and privacy actions/services live outside surface controllers for future User/Admin reuse. |
| Versioned JSON contract | implemented | Shared success/error envelopes, 14 public operations, and all 70 identity/User/Admin operations are represented by route-bound OpenAPI 3.1 contracts. The 20 organization/platform/storage operations use specific request/response schemas. |
| Correlation identifiers | implemented | `AssignCorrelationId` validates or creates UUIDs and returns `X-Correlation-ID`. |
| Liveness and readiness | implemented | Liveness is process-only; readiness safely probes database, cache, and the configured queue backend without returning connection details. |
| Public API rate limiting | implemented | Public v1 routes use the configurable `public-api` limiter and normalized `429 RATE_LIMIT_EXCEEDED` responses. |
| MySQL configuration | implemented | MySQL 8.4 is the runtime default, the connection pins InnoDB, PHPUnit uses `family_house_connect_testing`, and the complete migration history passes an authoritative fresh rebuild. |
| Optional S3-compatible storage | implemented | Local storage is the safe default. Encrypted MySQL configuration, SSRF-safe connection probing, activation/deactivation, runtime disk resolution, the Flysystem S3 adapter, and 5 global/recent-MFA/permission/rate-limited audited Admin operations are implemented without making S3 a boot dependency. |
| Redis cache/queue | planned | Redis cache/queue configuration, readiness coverage, worker template, and a disposable Redis CI service are implemented. The hosted workflow and production Redis service are not yet verified. |
| Queue workers and scheduler | implemented | Supervisor/cron deployment templates plus protected failed-job and batch pruning schedules are present and tested. Production process supervision and heartbeat monitoring remain environment gates. |
| Structured logging | implemented | NDJSON file/stderr logging includes correlation context, recursively redacts sensitive keys, and keeps stack traces opt-in. |
| CI quality gate | implemented | GitHub Actions defines PHP 8.3, MySQL 8.4, Redis, migrations, Composer validation/audit, PHPUnit, and Pint enforcement; the hosted workflow has not yet run. |
| Backup, restore, and monitoring | implemented | MySQL backup/restore and monitoring runbooks exist; a local isolated MySQL 8.4 restore drill matched tables, engines, migrations, and representative counts. Production recovery objectives and observability remain OD-007/OD-012 gates. |
| Authentication, MFA, sessions/devices | implemented | Same-origin CSRF-protected sessions, registration/login/logout, signed verification, generic password recovery, device-bound rotating mobile credentials, refresh-reuse family revocation, encrypted TOTP, hashed single-use recovery codes, recent-MFA evidence, sessions/devices, suspension, consent, and preferences are implemented and HTTP-tested. |
| Permission + scope + record policy authorization | implemented | Explicit bundles, permissions, grants, assignments, global/geography/exact/own-record containment, recorded allow/deny decisions, recent-MFA Admin enforcement, scoped queries, BOLA-safe 404s, and no-self-suspension policy are implemented. Broader delegation and domain-specific record policies remain. |
| Canonical Person identity | implemented | `Person` is the canonical root with opaque ULID, one `PersonProfile`, optional one-to-one `User`, locking, conflict checks, and audited idempotent linkage. |
| Generic geography hierarchy | implemented | Country-configurable levels, same-country immediate-level units, cycle-safe moves, reusable locations, IANA timezones, coordinates, country/unit scope containment, and 9 scoped Admin management operations are implemented and tested. |
| Platform configuration and feature flags | implemented | Typed environment/scope overrides, encrypted confidential values, deterministic rollout, activation windows, cache invalidation, locking, audit, and 6 scoped Admin management operations are tested; confidential values remain redacted and no unapproved keys/flags are seeded. |
| Secure files/media foundation | implemented | Private quarantined assets record owner, purpose, classification, content-detected MIME, size, hash, idempotency, actual storage provider/location revision, and audit. A scanner provider, approval workflow, signed delivery, and protected APIs remain gated. |
| Audit and access decisions | implemented | Append-only audit/access-decision records plus global-only, recently MFA-verified, explicitly permissioned, minimized review APIs are implemented. Database-role restrictions and retention remain deployment/policy gates. |
| Church and Home Church | implemented | Church/location scope, applications/transitions, first timers and follow-up now have 9 protected Admin operations with country/unit/church containment, strict requests, minimized resources and audit, alongside public discovery/application APIs. Broader pastoral workflows remain. |
| Mission and soul follow-up | implemented | Crusade/soul listing, canonical-person capture, mentor assignment and follow-up/completion now have 6 protected Admin operations with country/unit/crusade containment, idempotency and audit. Partners/stories/support remain. |
| KCA lifecycle | implemented | Application/admission, year/cohort/enrollment, curriculum, attendance, assignments/evidence/reviews, assessments, and policy-gated immutable certificates are present. Public HMAC-backed certificate verification is registered; OD-008 thresholds, fees, signer and revocation policy, and protected APIs remain. |
| Press publication and translation | implemented | Publication/translation persistence, exact transitions, canonical contributors, ISBN validation, idempotency, audit, protected workflow state, and public published catalogue/detail APIs are tested. Authority policy and distribution/provider integration remain. |
| Events and finance | planned | Public published upcoming event list/detail plus free-event registration/attendance/feedback foundations are implemented. Payment intents, immutable transactions, reconciliation, receipts, refunds, and disputes have a provider-neutral foundation, but governance/webhook defaults deny and OD-009/provider/protected API work remains. |
| Communications and audiences | implemented | Templates, server-side audience rules, consent/scope/guardian hooks, recipient snapshots, broadcasts, delivery attempts, and local in-app notifications are present with idempotency/audit. Guardian and outbound-provider defaults deny; APIs remain absent. |
| Reporting, alerts, search, and advisory AI | planned | Canonical metric definitions, alert-rule/occurrence lifecycle, classification-filtered null-provider search, and provider-disabled human-decision-only AI services exist. Dashboard queries, approved policies/providers, and APIs remain. |
| Safeguarding/privacy workflows | planned | Guardian/child, encrypted restricted incidents, data-subject requests, and private export-artifact lifecycle exist with default-deny access/execution. OD-006/OD-007 policy, deletion execution, API authorization, and integrated migration verification remain. |
| Public OpenAPI-generated TypeScript/Dart clients | implemented | Deterministic public-only clients are generated from `openapi/public-v1.openapi.json`; route/contract/staleness checks cover all 14 public operations, including the idempotent write, and both clients compile/analyze. |
| Protected TypeScript/Dart clients | implemented | Deterministic protected clients cover all 70 identity/User/Admin operations, expose cookie/CSRF and opaque bearer/device transports plus scope headers, and pass TypeScript/Dart analysis. Organization/platform/storage contracts use specific schemas and typed TypeScript inputs/results. |
| Next.js and Flutter live integration | blocked | Configuration/transport examples exist, but the adjacent Next.js and Flutter applications do not import the generated packages and no cross-repository E2E run is proven. |

Client requirement sources reviewed:

- `../frontend/REQUIREMENTS_TRACEABILITY.md`
- `../frontend/Family_House_Connect_NextJS_Design_Closure_Developer_Handoff.docx`
- `../mobile/REQUIREMENTS_TRACEABILITY.md`
- `../mobile/OPEN_DECISIONS.md`
