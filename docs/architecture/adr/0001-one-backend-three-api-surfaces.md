# ADR 0001: One Backend with Three API Surfaces

Status: accepted

Date: 2026-08-25

## Decision

Family House Connect uses one Laravel modular monolith and one canonical data model. Public, authenticated user, and privileged admin APIs are route/authorization/projection surfaces over shared application and domain services.

External prefixes are `/api/v1`, `/api/v1/user`, and `/api/v1/admin`. Prefixes do not grant authorization.

## Consequences

- Next.js and Flutter consume the same domain semantics and public identifiers.
- Surface controllers stay thin and may select different requests/resources while calling shared services.
- Protected operations enforce authentication, permission, scope, record policy, workflow eligibility, and data-classification rules in Laravel.
- Domain-specific identity tables cannot replace the canonical Person identity.
- Microservices require a later ADR justified by operational/domain needs; they are not the starting architecture.
