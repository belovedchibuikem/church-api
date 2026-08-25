# API Catalog

Implemented operations only; planned routes are not presented as production endpoints.

| Endpoint | Surface/domain | Controller/action | Auth / permission / scope | Request / response | Workflow / audit | Tests | OpenAPI operation ID |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1` | Public / Platform | `ApiStatusController` / status query | Public | No body / `ApiStatusEnvelope` | None / correlation context | `ApiFoundationTest` | `getApiStatus` |
| `GET /api/v1/health` | Public / Platform | `HealthController` / liveness query | Public | No body / `HealthEnvelope` | None / correlation context | `ApiFoundationTest` | `getHealth` |
| `GET /api/v1/health/readiness` | Public / Platform | `ReadinessController` / `ReadinessChecker` | Public; `public-api` rate limit | No body / `ReadinessEnvelope` or `SERVICE_NOT_READY` | Read-only dependency probes / correlation context | `ReadinessControllerTest` | `getReadiness` |

Reserved surface prefixes:

- Public: `/api/v1/...`
- Authenticated user/member: `/api/v1/user/...`
- Privileged administration: `/api/v1/admin/...`

The user and admin route files intentionally contain no placeholder endpoint while authentication and policy foundations are unresolved.

All implemented API routes return the shared success envelope or a stable-code error envelope. Public routes are throttled; validation, authentication, authorization, not-found, method, rate-limit, service, and unexpected failures are normalized without exposing exception details.
