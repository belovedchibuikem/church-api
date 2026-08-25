# Canonical Domain Model

This is the approved conceptual model. The canonical identity root and audit foundation are now implemented; authentication, authorization, organization, and ministry lifecycle records remain phased work.

## Identity root

- `Person` is the canonical human identity and owns an opaque ULID used by future external contracts.
- `PersonProfile` is a one-to-one record containing names; additional confidential profile/contact fields require explicit contracts.
- `User` is an optional login account linked to at most one Person, and each Person may have at most one User. Linking is transactional, idempotent, conflict-safe, and audited.
- Credentials, MFA methods, sessions, devices, consents, preferences, role assignments, and scope assignments reference that identity.
- Visitor, first-timer, convert, disciple, member, worker, mentor, KCA student, mission participant, leader, alumni, and partner are lifecycle records or assignments—not duplicate Person tables.

## Organization root

- `Country` owns configurable `AdministrativeLevel` definitions.
- `AdministrativeUnit` forms a parent/child hierarchy and references its country and level.
- `Location` stores reusable address, coordinates, timezone, and map metadata.
- `Church`, `HomeChurch`, KCA, and Mission records reference organizational units and locations rather than hard-coded Nigerian divisions.

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
