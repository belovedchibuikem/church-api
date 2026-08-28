# Open Decisions

Updated: 2026-08-26

## Resolved implementation decisions

- **OD-001 — Browser authentication transport (resolved):** Laravel owns the first-party browser session. Next.js uses same-origin or explicitly configured same-site cookies, obtains the CSRF cookie before state changes, and never bypasses Laravel authentication through a trusted BFF header. Login regenerates the session; logout invalidates it and rotates the CSRF token. Production cookies are Secure, HttpOnly where applicable, and SameSite=Lax unless an approved deployment requires a stricter setting.
- **OD-002 — Mobile credential lifecycle (resolved):** mobile clients use an opaque 15-minute access credential and a one-time 30-day refresh credential. Only keyed hashes are stored. Credentials are bound to a registered device and security session; refresh rotation is mandatory, refresh reuse revokes the whole credential family/session, and account/device/session revocation invalidates access immediately. Permanent tokens are prohibited.
- **OD-003 — MFA policy (resolved):** authenticator-app TOTP and single-use recovery codes are the only enabled methods. TOTP secrets are encrypted and recovery codes are hashed. Email/SMS MFA is disabled. Every Admin API and designated sensitive User operation requires MFA verified within the previous 12 hours; setup, challenge, recovery, and retry paths are rate limited and audited.
- **OD-004 — Permission bundles (resolved for the registered protected foundation):** authorization uses stable explicit permission codes, never role-name or wildcard shortcuts. The registered bundles are member security self-service, platform identity/access administration, organization/geography administration, and platform settings/storage administration. No operational administrator is seeded. Privilege grants, role changes, and scope changes require a separately authorized, recently MFA-verified actor and cannot silently grant privileges beyond that actor's explicit permission/scope.
- **OD-005 — Scope semantics (resolved conservatively):** global contains organizational scopes; country and configurable administrative-unit containment use the database-backed hierarchy. Church, Home Church, KCA cohort, and Mission/crusade scopes are exact-match until their domain hierarchy is explicitly approved. Own-record scope matches only the authenticated canonical Person/User and never broadens another scope. Unknown scope types deny except for exact assignment matches.

## Open decisions
- **OD-006 — Restricted records:** approve record-level access and enhanced monitoring for counselling, pastoral, safeguarding, and child data by jurisdiction.
- **OD-007 — Retention and privacy:** approve retention, export, erasure, legal-hold, and anonymization rules by data class and country.
- **OD-008 — KCA governance:** approve pass thresholds, prerequisite rules, fees, certificate signers, and revocation authority.
- **OD-009 — Providers:** select payment, messaging, streaming, maps, and AI providers by region. Storage has selected an optional S3-compatible adapter contract; the deployment-specific S3 vendor, endpoint, bucket, region, and credential policy remain open.
- **OD-010 — Payment governance:** approve supported currencies, legal entities, gateway routing, refund authority, reconciliation rules, and webhook retention.
- **OD-011 — Support impersonation:** remains disabled unless explicit governance approval defines eligibility, consent, time limit, and audit controls.
- **OD-012 — Production infrastructure:** local MySQL 8.4 is verified. Supply production MySQL, Redis, optional S3-compatible storage credentials, queue, mail, observability, backup, and restore environments for validation.
