# Security Model

## Trust boundaries

- Laravel is the final authorization and workflow boundary for Next.js, Flutter, and future integrations.
- Client route guards and hidden controls are usability features, never security controls.
- Every protected request is re-authorized against permission, organizational scope, target record, workflow state, and data classification.

## Baseline controls

- Versioned API routes and purpose-specific response projections.
- UUID correlation IDs propagated in the response and request log context.
- JSON exception rendering for every `/api/*` request, including unmatched routes, with stable error codes and no stack traces, SQL text, internal paths, or exception messages.
- Configurable IP-keyed throttling on the public API surface, with separate stricter profiles still required for authentication and sensitive writes.
- Public readiness reports only `ok`/`failed` for database, cache, and queue; it does not expose drivers, hosts, database names, credentials, or exception details.
- No user/admin endpoint exists until authentication and authorization are wired.
- Secrets remain outside source control; application code reads configuration, not environment variables directly.
- Optional S3 credentials are encrypted at rest with the application key, hidden from model serialization, and never returned by an API. Connection failures persist stable codes rather than provider exception text.
- Local storage stays active until an S3 configuration passes a write/read/delete probe. A failed activation or revalidation leaves new writes on local storage.
- Canonical identity linkage uses row locks, one-to-one database constraints, idempotency, and a mandatory audit event. `person_id` is not mass assignable on `User`.
- Audit events reject model updates and deletes, use opaque ULIDs, and capture the request correlation ID without requiring an HTTP controller.

## Required controls before protected APIs

- Verified browser/mobile authentication, session/device revocation, MFA, suspension, and risk checks.
- Permission policies plus hierarchical scope containment and record-level restrictions.
- Immutable append-only audit events and access-decision records for restricted reads and privileged mutations.
- Rate limits for auth/public writes, idempotency for retryable writes, signed webhooks, secure file validation/scanning, and privacy-safe structured logs.
- Field-level API resources that never serialize restricted attributes into broader projections.
- Storage administration requires authentication, explicit global permissions, recent MFA, endpoint/SSRF validation, throttling, and immutable secret-safe audit events before routes may be registered.

## Threat focus

BOLA/IDOR, privilege escalation, cross-scope access, mass assignment, injection, sensitive logging, token leakage, webhook replay/spoofing, payment manipulation, malicious uploads, and queue duplicate delivery are release-blocking concerns.
