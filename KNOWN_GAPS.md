# Known Gaps

- Local MySQL 8.4 connectivity, migrations, and an isolated logical backup/restore drill are verified. Production MySQL backup/restore, recovery objectives, and Redis remain unverified.
- Authentication, CSRF/session transport, mobile token renewal, MFA, devices, revocation, permissions, scopes, policies, and audit storage are not implemented.
- Canonical Person/Profile and optional User linkage now exist, but the legacy `users.name` field remains until registration/profile contracts are approved and migrated.
- No business-domain migrations, models, services, resources, jobs, events, providers, or APIs exist.
- Stable error envelopes cover framework validation, authentication, authorization, not-found, method, rate-limit, service, and unexpected failures. Domain-specific conflict/workflow mappings will be added with their owning modules.
- OpenAPI covers only the two implemented foundation operations; no generated clients exist.
- The optional S3-compatible core and adapter are installed and contract-tested, but no live bucket/credentials have been supplied. Queue/scheduler configuration, structured logging, CI, monitoring guidance, and local restore have evidence; mail and production infrastructure do not.
- Redis is configured for production-style cache/queue use and the CI workflow supplies Redis plus `phpredis`, but this machine has neither a Redis service nor the PHP Redis extension. The hosted CI workflow and production Redis readiness remain unverified.
- The local restore drill left a 61 KB plaintext development dump at `C:\Users\BELOVED\AppData\Local\Temp\fhc-restore-verify-20260825-2300\family_house_connect.sql` because the execution sandbox denied file deletion. The isolated restore database was removed. Delete the dump when host policy permits.
- Storage administration routes are intentionally absent until authentication, global permissions, MFA, endpoint/SSRF controls, rate limiting, and immutable audit storage are available.
- Audit immutability is enforced through the Eloquent model. Production database credentials still need insert/select-only audit-table privileges, retention controls, backup validation, and access-decision records.
- Identity/profile factories exist for tests; no operational people, users, or audit events are seeded because these are not reference data.
- Future media/file records must retain their storage provider and object key; switching the active provider applies only to new writes and is not a migration of existing objects.
- Next.js and Flutter remain fixture-driven and are not connected to this API.
- The Next.js handoff DOCX was text-extracted, but visual rendering could not run because LibreOffice is unavailable on this machine.
