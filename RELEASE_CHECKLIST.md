# Release Checklist

Current release status: **not production ready**.

- [x] Laravel boots and framework dependencies are locked.
- [x] Public/user/admin route files are separated under API v1.
- [x] Public status/health contracts and correlation IDs are tested.
- [x] Dependency readiness, normalized API errors, and configurable public throttling are tested and documented.
- [x] Initial OpenAPI and implementation control documents exist.
- [x] Local MySQL 8.4 connectivity and migrations are verified with InnoDB tables.
- [x] Optional S3-compatible adapter and encrypted activation-gated storage core are implemented.
- [x] Canonical Person/Profile schema, conflict-safe User linkage, and append-only audit writer are implemented and tested.
- [x] Privacy-safe structured JSON logging and correlation context are tested.
- [x] Queue worker/scheduler templates and protected retention schedules are implemented and tested.
- [x] MySQL 8.4 plus Redis CI services and dependency/test/format gates are defined.
- [x] A local isolated MySQL backup/restore drill passed and operational runbooks exist.
- [ ] Production environment validation and secret injection complete.
- [ ] Production MySQL migration, connectivity, backup, and restore verified.
- [ ] Live S3-compatible connection, least-privilege credentials, encryption, lifecycle, and restore behavior verified if object storage is enabled.
- [ ] Redis cache, queue, retry, failure, and monitoring verified.
- [x] Scheduler task registration and single-run/overlap controls verified locally.
- [ ] Hosted CI, supervised workers, scheduler heartbeat, Redis, monitoring, and restore drills verified in the production-like environment.
- [ ] Authentication, MFA, session/device revocation, and suspension verified.
- [ ] Permission, scope, record-policy, and restricted-data negative tests pass.
- [ ] Audit/access-decision storage and privileged transition coverage pass.
- [ ] Secure file/media, webhook, payment, privacy, and safeguarding controls pass.
- [ ] OpenAPI is complete and TypeScript/Dart clients build.
- [ ] Next.js and Flutter critical E2E journeys pass against real APIs.
- [ ] Dependency, static analysis, security, performance, and observability gates pass.
- [ ] No critical security/authorization finding or undocumented production placeholder remains.
