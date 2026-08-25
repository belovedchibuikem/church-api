# Open Decisions

Updated: 2026-08-25

- **OD-001 — Browser authentication transport:** approve the exact first-party Next.js-to-Laravel cookie/session and CSRF architecture, including domains and BFF responsibilities.
- **OD-002 — Mobile credential lifecycle:** approve access lifetime, renewal/rotation, device binding, revocation, and secure recovery behavior. Permanent tokens are prohibited.
- **OD-003 — MFA policy:** approve required roles/actions and the allowed authenticator, email, SMS, and recovery methods.
- **OD-004 — Ecclesiastical permissions:** approve the final permission catalogue, role bundles, delegation rules, and separation-of-duties constraints.
- **OD-005 — Scope semantics:** approve containment and assignment rules for global, country, configurable administrative units, church, Home Church, KCA, Mission/crusade, and own-record scopes.
- **OD-006 — Restricted records:** approve record-level access and enhanced monitoring for counselling, pastoral, safeguarding, and child data by jurisdiction.
- **OD-007 — Retention and privacy:** approve retention, export, erasure, legal-hold, and anonymization rules by data class and country.
- **OD-008 — KCA governance:** approve pass thresholds, prerequisite rules, fees, certificate signers, and revocation authority.
- **OD-009 — Providers:** select payment, messaging, streaming, maps, and AI providers by region. Storage has selected an optional S3-compatible adapter contract; the deployment-specific S3 vendor, endpoint, bucket, region, and credential policy remain open.
- **OD-010 — Payment governance:** approve supported currencies, legal entities, gateway routing, refund authority, reconciliation rules, and webhook retention.
- **OD-011 — Support impersonation:** remains disabled unless explicit governance approval defines eligibility, consent, time limit, and audit controls.
- **OD-012 — Production infrastructure:** local MySQL 8.4 is verified. Supply production MySQL, Redis, optional S3-compatible storage credentials, queue, mail, observability, backup, and restore environments for validation.
