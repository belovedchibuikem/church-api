# OpenAPI source of truth

`public-v1.openapi.json` is the only authoritative OpenAPI document in this repository.
It describes exactly the registered public v1 status, liveness, and readiness operations.

The former `openapi.yaml` was removed because it used a different server/path composition
and could be mistaken for a second contract. Do not recreate a hand-maintained YAML copy.
Generate TypeScript and Dart clients directly from `public-v1.openapi.json` with
`composer clients:generate`, and run `composer clients:integration-check` before commit.

Protected User/Admin operations remain absent until their authentication, permission,
scope, record-policy, and workflow contracts are approved and registered in Laravel.
