# Client Integration

Generated clients are produced from OpenAPI plus the generation manifests. They are
HTTP operation clients, not a production platform: they do not select a PSP, send
mail, talk to live Redis/S3, or prove browser/mobile E2E.

## Public operations

`clients/public-client-generation.json` drives `clients/typescript/src/public-api.ts`
and `clients/dart/lib/public_api.dart`. The registered public operations are:

| Client method | HTTP |
| --- | --- |
| `getApiStatus()` | `GET /api/v1` |
| `getHealth()` | `GET /api/v1/health` |
| `getReadiness()` | `GET /api/v1/health/readiness` |
| `listChurches()` / `getChurch()` | `GET /api/v1/churches`, `GET /api/v1/churches/{church}` |
| `listPressPublications()` / `getPressPublication()` / `downloadPressPublication()` | `GET /api/v1/press/publications`, `GET …/{publicId}`, `GET …/{publicId}/download` |
| `listEvents()` / `getEvent()` | `GET /api/v1/events`, `GET /api/v1/events/{event}` |
| `verifyKcaCertificate()` | `GET /api/v1/kca/certificates/verify` |
| `listMissionLocations()` / `listMissionCrusades()` / `getMissionCrusade()` | `GET /api/v1/mission/locations`, `GET /api/v1/mission/crusades`, `GET …/{crusade}` |
| `getMapsConfiguration()` / `listMapsPlaces()` | `GET /api/v1/maps/configuration`, `GET /api/v1/maps/places` |
| `reconcilePublicPaymentWebhook()` / `recordPublicPaymentDisputeWebhook()` | `POST /api/v1/finance/webhooks/reconcile`, `POST …/disputes` (fail-closed without a live verifier) |
| `listContentPages()` / `getContentPage()` | `GET /api/v1/content/pages`, `GET /api/v1/content/pages/{slug}` |
| `submitHomeChurchApplication()` | `POST /api/v1/home-church-applications` |

That is 21 public operations. Public clients do not implement browser sessions,
mobile credentials, MFA, User APIs, or Admin APIs.

## Protected operations

Protected TypeScript and Dart clients are generated from
`clients/protected-client-generation.json` against `openapi/protected-v1.openapi.json`
(192 operations: identity, User, and Admin). Outputs:

- `clients/typescript/src/protected-api.ts`
- `clients/dart/lib/protected_api.dart`

Regenerate with `composer clients:generate`. Do not hand-edit generated files.

## Next.js / web

The API repository still ships `clients/typescript/examples/nextjs-public-health.ts`
as a standalone health example. The adjacent web app imports the generated
TypeScript clients through `frontend/apps/web/lib/generated-api.ts`
(`FamilyHousePublicApiClient` / `FamilyHouseProtectedApiClient`).

Today `lib/site-api.ts` uses that wrapper for `getHealth()`. Other public catalogue
calls still go through `lib/public-api.ts`. Admin overlays dispatch registered
mutations to Laravel via `lib/admin-mutation-dispatcher.ts` (`executeAdminAction`).

Configure one absolute origin (the generated client appends `/api/v1/...`):

```dotenv
NEXT_PUBLIC_FHC_API_BASE_URL=https://api.example.org
```

The example validates the URL, creates `FamilyHousePublicApiClient`, sends one UUID
in `X-Correlation-ID` across the status checks, and converts `PublicApiError` into a
safe UI-facing shape containing HTTP status, application error code, message, and
the server correlation ID. Do not display `error.details` without an operation-specific
projection.

For a local package check:

```shell
cd clients/typescript
npm install
npm run typecheck
```

The web app is outside this API repository's build/release boundary. Importing the
generated health client does not prove a production deployment, live mail, or E2E.

## Flutter / mobile

The package name is `family_house_connect_public_api` (`clients/dart/pubspec.yaml`).
The adjacent `../mobile` app path-depends on it and constructs
`FamilyHousePublicApiClient` from `AppServices`. Mobile auth uses the generated
protected Dart client; other `/user` and Admin calls go through `HttpApiTransport`.

Use `clients/dart/lib/public_api.dart`. For Flutter mobile or desktop,
`clients/dart/lib/io_public_api_transport.dart` provides a dependency-free `dart:io`
transport. Flutter web must supply a browser-safe `PublicApiTransport`.

Configure the base URL at build/run time:

```shell
flutter run --dart-define=FHC_PUBLIC_API_BASE_URL=https://api.example.org
```

Or use the checked-in example file:

```shell
flutter run --dart-define-from-file=clients/dart/dart-define.example.json
```

`clients/dart/example/flutter_public_health.dart` validates the base URL, forwards an
optional correlation ID, and demonstrates extracting the stable error code and
correlation ID from `PublicApiException`.

For a standalone client check:

```shell
cd clients/dart
dart analyze
```

Mobile platform builds, store signing, and device E2E are not part of this API
repository and are not claimed as verified.

## Correlation and errors

Clients may omit a correlation ID and accept the server-generated value, or send a UUID in
`X-Correlation-ID`. Always retain the response envelope's `correlation_id` for support and
diagnostics. Do not treat it as authentication, authorization, or a secret.

Non-2xx responses use:

```json
{
  "error": {
    "code": "STABLE_APPLICATION_CODE",
    "message": "Safe message",
    "details": {}
  },
  "meta": {
    "api_version": "v1",
    "timestamp": "2026-08-26T00:00:00Z"
  },
  "correlation_id": "00000000-0000-4000-8000-000000000000"
}
```

Branch on `error.code`, not the English message. Readiness only reports safe `ok`/`failed`
dependency states and should not be used as an authorization or feature-entitlement signal.

## Verification and gates

Run `composer clients:integration-check`. It validates OpenAPI, verifies generated outputs
are current, and deterministically checks package metadata and public integration files.
This check needs PHP only and never connects to MySQL.

Protected operations stay aligned with Laravel routes registered in
`routes/api/v1/*.php` and `protected-client-generation.json`. Payments, outbound
messaging, advisory AI, and live S3 stay fail-closed until providers are selected
(see repo `docs/HONEST_LIMITS.md`). OD-006 through OD-010/012 still gate safeguarding
restricted reads, privacy deletion/legal-hold, KCA evidence/signers, and vendor
activation. No live PSP, mail, Redis, or E2E is claimed by these clients.
