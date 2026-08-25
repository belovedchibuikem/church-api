# Test Coverage Matrix

| Area | Current evidence | Required expansion |
| --- | --- | --- |
| API status contract | Feature test covers 200 response and envelope shape | Contract validation against generated clients. |
| Health contract | Feature tests cover unauthenticated liveness plus readiness success and safe `503` failure | Add live Redis readiness when infrastructure exists. |
| Structured logging | Real Monolog stream test covers JSON output, correlation context, recursive redaction, message interpolation, and absence of raw secrets | Add production log-ingestion/schema validation after an observability provider is selected. |
| Queue and scheduler | Feature test covers retention commands, schedules, overlap locks, and one-server execution flags; scheduler inventory resolves | Add a supervised worker integration test and scheduler heartbeat in the deployment environment. |
| CI infrastructure | Workflow statically validates MySQL 8.4, Redis, migrations, Composer audit, PHPUnit, and Pint gates | Run and retain evidence from the hosted workflow/service containers. |
| Backup and restore | Local MySQL 8.4 drill matched 13 tables, 13 InnoDB tables, 8 migrations, and representative counts | Run encrypted production-like restore drills and measure approved RPO/RTO after OD-007/OD-012. |
| API error contract | Feature tests cover `401`, `403`, `404`, `405`, `422`, `429`, safe `500`, and correlation on unmatched routes | Add domain conflict codes with each workflow module. |
| Correlation ID | Feature tests cover valid propagation, invalid replacement, and error propagation | Cover queued-job propagation. |
| Public throttling | Feature test proves the configured limit and normalized `429` response | Add stricter authentication, verification, search, write, payment, AI, and upload profiles with their endpoints. |
| Canonical identity | Feature tests cover opaque Person IDs, profile linkage, User mass-assignment protection, idempotent linking, both conflict directions, locking path, and audit creation | Add registration/profile API tests after authentication transport and field contracts are approved. |
| Audit foundation | Feature tests cover actor/target/scope/correlation/metadata persistence, action/target validation, and update/delete rejection | Add privileged mutation integration, access decisions, database-role restrictions, retention, and audit-read authorization. |
| Authentication/MFA | None | Registration, login, verification, MFA, expiry, revocation, suspension, rate limits. |
| Permission/scope/policy | None | Full policy matrices plus HTTP denial, cross-country/church, own-record, and restricted-record tests. |
| Domain workflows | None | Happy and invalid transitions, concurrency, audit, notification, and retry behavior. |
| Payments/webhooks | None | Signature, replay, duplicate delivery, reconciliation, refund/dispute authorization. |
| Storage provider configuration | Feature tests cover encrypted/hidden credentials, local fallback, revision invalidation, validation success/failure, S3 activation, revalidation fallback, and switching back to local | Add secured admin HTTP policy/MFA/audit tests and an explicitly gated disposable S3/MinIO integration test. |
| Files/media | Storage provider resolver only | Add provider-per-object persistence, MIME/content, size, malware/quarantine, signed access, retry/idempotency, and migration tests. |
| Cross-client contracts | None | OpenAPI lint, generated TypeScript/Dart clients, Next.js/Flutter builds and E2E journeys. |
