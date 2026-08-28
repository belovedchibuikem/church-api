# Canonical Domain Model

This is the approved conceptual model reconciled to the implemented foundations. Authentication transport and the initial protected authorization semantics are resolved; domain API breadth, provider selection, restricted-record rules, and ministry/governance policy remain phased work.

## Identity root

- `Person` is the canonical human identity and owns an opaque ULID used by future external contracts.
- `PersonProfile` is a one-to-one record containing names; additional confidential profile/contact fields require explicit contracts.
- `User` is an optional login account linked to at most one Person, and each Person may have at most one User. Linking is transactional, idempotent, conflict-safe, and audited.
- Account suspension/reactivation, consent evidence, preferences, devices, security-session records, hashed MFA verifier/recovery records, role assignments, and scope assignments reference that identity and use audited state-changing actions.
- Visitor, first-timer, convert, disciple, member, worker, mentor, KCA student, mission participant, leader, alumni, and partner are lifecycle records or assignments—not duplicate Person tables.

## Organization root

- `Country` owns configurable, ordered `AdministrativeLevel` definitions and uses an ISO alpha-2 code plus opaque public ID.
- `AdministrativeUnit` forms a parent/child hierarchy, requires a same-country parent at the immediately preceding configured level, prevents cycles, and references its country and level.
- `Location` stores reusable address, validated coordinates, and an IANA timezone; an attached administrative unit must belong to its country.
- `Church`, `HomeChurch`, KCA, and Mission records reference organizational units and locations rather than hard-coded Nigerian divisions.

Country scope contains units in that country, and an administrative-unit scope contains its descendants. Unknown scope types retain exact-match denial until their domains define containment.

## Platform services root

- `PlatformConfiguration` stores typed string/integer/boolean/JSON values by environment and optional exact scope. Confidential values are encrypted and hidden.
- `FeatureFlag` supports environment/scope overrides, activation windows, explicit enablement, and deterministic percentage rollout from opaque identifiers. Flags never bypass authorization.
- `FileAsset` stores metadata and a private object reference, never the binary. It records the provider/location used at write time, remains quarantined until scanning/approval, and uses content MIME, byte, checksum, ownership, classification, and idempotency controls.
- Communication templates and server-side audience rules produce consent/scope/guardian-evaluated recipient snapshots, retry-safe delivery attempts, and local in-app notifications. No client-supplied recipient list or configured outbound provider is authoritative.
- `AlertRule` is inactive by default; `AlertOccurrence` represents an open, acknowledged, or resolved condition and prevents duplicates for the same unresolved fingerprint. Evaluation and visibility policies default deny.
- `DataSubjectRequest` owns privacy request state. Export requests retain exact scope/category selections, a private subject-owned `FileAsset`, and an expiry state; OD-007 execution policy defaults deny and expiry does not silently delete the file.
- Search and advisory AI are provider-neutral boundaries. Search removes classifications outside the approved projection, while AI sanitizes context and can never remove the human-decision requirement.

## Ministry lifecycle roots

- Church/Home Church records reference generic locations and administrative units. Membership, Home Church applications, first-timer registration, and follow-up retain canonical Person history.
- Mission invitations, teams, soul journeys, mentor assignments, and follow-up interactions reference one canonical Person and retain auditable transitions.
- KCA applications and admission decisions lead to canonical-person enrollments within years/cohorts. Curriculum, assignments, evidence, reviews, assessments, and certificates remain linked to that enrollment; certification policy denies pending OD-008.
- Press publications retain canonical-person contributors and append-only publication/translation transition evidence.
- Events retain registration, attendance, and feedback. Finance retains provider-neutral intent, transaction, reconciliation, receipt, refund, and dispute records; provider verification/governance deny by default.

## Safeguarding root

- Child profiles, guardian relationships/consents, and safeguarding incidents reference canonical people. Incident detail is encrypted and hidden from serialization/audit metadata.
- Restricted-record access is a policy boundary that denies until OD-006 role, scope, guardian, and restricted-read semantics are approved.

## Cross-domain identity journeys

- Mission soul record → convert journey → church membership → KCA application uses one Person ID.
- KCA student → graduate → mentor preserves the Person ID.
- Event registration → payment → attendance preserves Event, Person, and Transaction IDs.
- Giving intent → payment transaction → reconciliation → receipt preserves one immutable transaction chain.

## Identifier policy

Internal relational keys and public opaque identifiers are separate concerns. External URLs/contracts use UUID/ULID-style opaque IDs; authorization never relies on obscurity.

## Audit root

- `AuditEvent` is append-only through the Eloquent model and is created through `RecordAuditEventAction`.
- Events support an actor, stable action code, target, scope, correlation ID, safe metadata, and occurrence time.
- Database-role-level insert/select-only enforcement and retention governance remain deployment decisions.
- `AccessDecision` is an append-only allow/deny record with permission, requested scope, stable reason, correlation ID, and the matched role assignment only for an allow.

## Authorization root

- Permissions are stable codes granted to roles through explicit grant records; role bundles are intentionally unseeded pending OD-004.
- Users receive time-bound/revocable role assignments and each effective assignment requires an explicit scope assignment.
- The safe default scope resolver matches exact type and key only. Country and administrative-unit containment are implemented; other hierarchical, global, own-record, and domain containment remain OD-005 policy work.
- Suspended users are denied before permission or scope evaluation. Protected operations must additionally apply session/MFA risk, record policy, workflow, and data-classification checks.
