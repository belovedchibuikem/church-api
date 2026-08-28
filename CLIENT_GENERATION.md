# Public API Client Generation

`openapi/public-v1.openapi.json` is the authoritative OpenAPI 3.1 contract for the currently registered public API. It contains 14 operations:

- `getApiStatus` — `GET /api/v1`
- `getHealth` — `GET /api/v1/health`
- `getReadiness` — `GET /api/v1/health/readiness`
- `listChurches` / `getChurch` — published Church discovery and detail
- `submitHomeChurchApplication` — idempotent public Home Church application submission
- `listPressPublications` / `getPressPublication` — public Press catalogue and detail
- `listEvents` / `getEvent` — published upcoming event catalogue and detail
- `verifyKcaCertificate` — opaque-code certificate verification
- `listMissionLocations` — mission-active location discovery
- `listMissionCrusades` / `getMissionCrusade` — mission crusade catalogue and detail

Run `composer clients:generate` after changing the contract. The deterministic generator reads `clients/public-client-generation.json` and writes:

- `clients/typescript/src/public-api.ts`
- `clients/dart/lib/public_api.dart`

Run `composer clients:check` in CI or before committing. It validates internal OpenAPI references, confirms the exact operation allow-list, and fails when generated output is stale.

The TypeScript client accepts an absolute deployment base URL and uses `fetch`. The Dart client is transport-neutral and includes an `HttpClient` adapter for mobile/desktop. Both clients accept an optional UUID correlation ID, support query/path inputs, surface structured API error envelopes, and require an idempotency key for the public write method.

No browser session, mobile credential, MFA, User API, or Admin API client is generated. Those contracts remain blocked by OD-001 through OD-005 and must not be added until their authentication, authorization, and scope semantics are approved and registered in Laravel.

Practical Next.js and Flutter configuration, transport, correlation/error handling, and
verification examples are documented in `clients/INTEGRATION.md`. Run
`composer clients:integration-check` for the PHP-only deterministic integration check.
