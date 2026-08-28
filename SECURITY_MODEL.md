# Security Model

## Trust boundaries

- Laravel is the final authorization and workflow boundary for Next.js, Flutter, and future integrations.
- Client route guards and hidden controls are usability features, never security controls.
- Every protected request is re-authorized against permission, organizational scope, target record, workflow state, and data classification.

## Baseline controls

- Versioned API routes and purpose-specific response projections.
- UUID correlation IDs propagated in the response and request log context.
- JSON exception rendering for every `/api/*` request, including unmatched routes, with stable error codes and no stack traces, SQL text, internal paths, or exception messages.
- Configurable IP-keyed public throttling plus HMAC-keyed browser/mobile login, recovery, verification, refresh, and MFA profiles.
- Public readiness reports only `ok`/`failed` for database, cache, and queue; it does not expose drivers, hosts, database names, credentials, or exception details.
- User/Admin endpoints use one dual-transport boundary: cookie requests initialize Laravel sessions and require CSRF, while opaque bearer requests require device binding and skip cookie session state.
- Secrets remain outside source control; application code reads configuration, not environment variables directly.
- Optional S3 credentials are encrypted at rest with the application key, hidden from model serialization, and never returned by an API. Connection failures persist stable codes rather than provider exception text.
- Local storage stays active until an S3 configuration passes a write/read/delete probe. A failed activation or revalidation leaves new writes on local storage.
- Canonical identity linkage uses row locks, one-to-one database constraints, idempotency, and a mandatory audit event. `person_id` is not mass assignable on `User`.
- Audit events reject model updates and deletes, use opaque ULIDs, and capture the request correlation ID without requiring an HTTP controller.
- Account suspension/reactivation, consent changes, device/session/MFA mutations, permission grants, role/scope assignments, and authorization decisions use transaction-safe records and stable audit codes.
- Devices use HMAC identifiers; TOTP secrets are encrypted; recovery values and opaque credentials are HMACed/hashed and hidden; revocation/verification/consumption state is not mass assignable.
- Suspended accounts cannot register devices, create security sessions, add MFA methods, or receive an allowed permission/scope decision.
- Authorization uses active role assignments, explicit permission grants, explicit scopes, an injectable containment resolver, and append-only allow/deny decision evidence. The resolver supports global, country/administrative hierarchy, canonical own-record, and approved exact-match domain scopes; unknown scope types remain exact-match only.
- Country and administrative-unit containment is database-backed, cross-country safe, descendant-aware, and cycle defensive; unknown domain scope types remain exact-match only.
- Confidential platform configuration is encrypted and hidden; configuration/flag changes are locked, cache-invalidated, and audited without values in audit metadata.
- File uploads are private, content-inspected, size-limited, checksumed, idempotent, and quarantined by default. Client filenames never become object keys, raw idempotency keys are not stored, and the safe scanner default never claims a clean result.
- File assets retain their actual provider/location revision. Credential rotation preserves that location; changing a referenced S3 bucket/endpoint is rejected until assets are migrated.
- Ministry workflow actions use row locks, canonical Person references, stable idempotency evidence, protected workflow fields, and safe audit metadata. These domain services do not imply that a protected HTTP authorization boundary exists.
- Communications resolve recipients from server-side audience rules, exclude suspended accounts, apply consent/preferences, and require guardian/scope policies. Guardian targeting and outbound delivery fail closed until approved policies/providers exist; raw retry keys are HMACed and hidden.
- Safeguarding incidents encrypt and hide restricted summaries and never copy them into audit metadata. Guardian relationships start pending, and restricted-record access defaults deny.
- Data-export execution defaults deny pending OD-007. Approved artifacts must be available confidential/restricted files owned by the data subject; category selections are encrypted/hidden, completion is immutable/idempotent, and expiry changes lifecycle state without silently deleting the asset.
- Alert rules are inactive by default. Evaluation and visibility are separate injectable policies that default deny; unresolved condition fingerprints are HMACed and deduplicated, and summaries are encrypted/hidden.
- Search defaults to a no-side-effect provider and filters results by allowed classification. Advisory AI defaults disabled, strips restricted/secret context keys, restricts assistant/use-case combinations, and always requires a human decision.
- Finance provider verification and payment governance default deny. No unverified webhook may create authoritative financial records.
- Generated TypeScript/Dart clients cover 14 public and 94 identity/User/Admin operations. Protected transports expose cookie/CSRF or opaque bearer/device credentials and explicit Admin scope headers without making client-side authorization decisions.
- Organization/geography Admin mutations enforce requested-scope-to-record containment in addition to permission middleware; collection queries are constrained to global, country, or administrative-unit subtrees before pagination. Country creation is global-only.
- Platform configuration/feature-flag Admin APIs enforce context containment, recent MFA, explicit permissions, audit every mutation, encrypt confidential values, and return only redacted confidential projections.
- Optional object-storage Admin APIs are global-only, recently MFA-verified, explicitly permissioned, mutation-rate-limited, and audited. Credentials are encrypted and write-only; endpoints must be HTTPS, resolve only to public addresses, and exclude metadata/private/loopback targets. Activation always performs a write/read/delete probe and failed validation leaves local storage active.
- Remaining-domain Admin catalog APIs are global-only, recently MFA-verified, explicitly permissioned read lists over an allowlisted registry. Projections omit encrypted, hashed, and internal storage columns; workflow mutations stay blocked until OD-006–OD-010 authority settles.

## Protected boundary and remaining domain gates

- Browser/mobile authentication, credential rotation/reuse denial, MFA challenges/recovery, active account/session checks, and recent-MFA Admin enforcement are implemented.
- Initial explicit bundles plus global/geography/exact/own-record containment and Admin account record policy are implemented; broader delegation and domain-specific restrictions remain.
- Immutable append-only audit events and access-decision records for restricted reads and privileged mutations.
- Rate limits for auth/public writes, idempotency for retryable writes, signed webhooks, secure file validation/scanning, and privacy-safe structured logs.
- Field-level API resources that never serialize restricted attributes into broader projections.
- Approved safeguarding/guardian, alert visibility, export scope/retention, payment, KCA certification, and communications-recipient policies; current defaults deny where decisions remain open.
- Storage administration requires authentication, explicit global permissions, recent MFA, endpoint/SSRF validation, throttling, and immutable secret-safe audit events before routes may be registered.

## Threat focus

BOLA/IDOR, privilege escalation, cross-scope access, guardian/child bypass, restricted-record disclosure, unsafe export, mass assignment, injection, sensitive logging, token leakage, webhook replay/spoofing, payment manipulation, malicious uploads, alert/communication recipient leakage, AI data disclosure, and queue duplicate delivery are release-blocking concerns.
