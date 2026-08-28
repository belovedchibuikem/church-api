# API Catalog

Implemented operations only; planned routes are not presented as production endpoints.

| Endpoint | Surface/domain | Controller/action | Auth / permission / scope | Request / response | Workflow / audit | Tests | OpenAPI operation ID |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1` | Public / Platform | `ApiStatusController` / status query | Public | No body / `ApiStatusEnvelope` | None / correlation context | `ApiFoundationTest` | `getApiStatus` |
| `GET /api/v1/health` | Public / Platform | `HealthController` / liveness query | Public | No body / `HealthEnvelope` | None / correlation context | `ApiFoundationTest` | `getHealth` |
| `GET /api/v1/health/readiness` | Public / Platform | `ReadinessController` / `ReadinessChecker` | Public; `public-api` rate limit | No body / `ReadinessEnvelope` or `SERVICE_NOT_READY` | Read-only dependency probes / correlation context | `ReadinessControllerTest` | `getReadiness` |
| `GET /api/v1/churches` | Public / Church | `ChurchController@index` / `PublicChurchQuery` | Public; `public-api` + `public-catalogue` limits | Allowlisted name/country/unit filters, sort, pagination / public-safe Church collection | Published records only; read-only / correlation context | `ChurchControllerTest` | `listChurches` |
| `GET /api/v1/churches/{church}` | Public / Church | `ChurchController@show` / `PublicChurchQuery` | Public; ULID binding; `public-api` + `public-catalogue` limits | ULID path / public-safe Church resource | Published record only; read-only / correlation context | `ChurchControllerTest` | `getChurch` |
| `POST /api/v1/home-church-applications` | Public / Home Church | `HomeChurchApplicationController` / `SubmitPublicHomeChurchApplicationAction` → `CreateHomeChurchApplicationAction` | Public; dedicated IP/contact throttle | Strict body plus `Idempotency-Key` / application receipt only | Creates Draft application, canonical Person/profile, HMAC idempotency and audit atomically | `HomeChurchApplicationControllerTest` | `submitHomeChurchApplication` |
| `GET /api/v1/press/publications` | Public / Press | `PressPublicationController@index` / `PublicPressPublicationQuery` | Public; `public-api` + `public-catalogue` limits | Allowlisted language/category/format filters, sort, pagination / minimized publication list | Published/distribution and available records only | `PublicPressCatalogueApiTest` | `listPressPublications` |
| `GET /api/v1/press/publications/{publicId}` | Public / Press | `PressPublicationController@show` / `PublicPressPublicationQuery` | Public; ULID binding; catalogue throttle | ULID path / publication plus approved translation metadata | No manuscript, asset, price, workflow, or translated-content disclosure | `PublicPressCatalogueApiTest` | `getPressPublication` |
| `GET /api/v1/events` | Public / Events | `PublicEventController@index` / `ListPublicEventsQuery` | Public; catalogue throttle | Strict category/date/sort/pagination / minimized event list | Published, non-ended records only | `PublicEventControllerTest` | `listEvents` |
| `GET /api/v1/events/{event}` | Public / Events | `PublicEventController@show` / `FindPublicEventQuery` | Public; ULID binding; catalogue throttle | ULID path / minimized event resource | Published, non-ended record only | `PublicEventControllerTest` | `getEvent` |
| `GET /api/v1/kca/certificates/verify` | Public / KCA | `VerifyKcaCertificateController` / `VerifyKcaCertificateQuery` | Public; dedicated verification throttle | Opaque code / verification facts only | HMAC lookup; generic not-found result; no learner/hash disclosure | `VerifyKcaCertificateControllerTest` | `verifyKcaCertificate` |
| `GET /api/v1/mission/locations` | Public / Mission | `PublicMissionLocationController` / `ListPublicMissionLocationsQuery` | Public; catalogue throttle | Strict country/search/status/pagination / minimized locations | Locations must have matching mission activity | `PublicMissionApiTest` | `listMissionLocations` |
| `GET /api/v1/mission/crusades` | Public / Mission | `PublicCrusadeController@index` / `ListPublicCrusadesQuery` | Public; catalogue throttle | Strict filters/sort/pagination / minimized crusade list | Upcoming by default; past/all explicit | `PublicMissionApiTest` | `listMissionCrusades` |
| `GET /api/v1/mission/crusades/{crusade}` | Public / Mission | `PublicCrusadeController@show` / `FindPublicCrusadeQuery` | Public; ULID binding; catalogue throttle | ULID path / minimized crusade resource | Read-only / correlation context | `PublicMissionApiTest` | `getMissionCrusade` |
| `GET /api/v1/maps/configuration` | Public / Maps | `PublicMapsController@configuration` | Public; catalogue throttle | Active provider + client key or Leaflet tile URL | Falls back to Leaflet/OSM when inactive | `AdminMapsProviderApiTest` | `getMapsConfiguration` |
| `GET /api/v1/maps/places` | Public / Maps | `PublicMapsController@places` | Public; catalogue throttle | Optional near/radius filters / geocoded churches & home churches | Coordinates only for published/active places | `AdminMapsProviderApiTest` | `listMapsPlaces` |

## Identity, User, and Admin operations

| Endpoint | Surface | Boundary | OpenAPI operation ID |
| --- | --- | --- | --- |
| `GET /api/v1/auth/csrf-cookie` | Browser identity | Web session bootstrap; browser throttle | `getBrowserCsrfCookie` |
| `POST /api/v1/auth/register` | Browser identity | CSRF; HMAC-keyed registration throttle | `registerBrowserUser` |
| `POST /api/v1/auth/login` | Browser identity | CSRF; HMAC-keyed credential throttle | `browserLogin` |
| `POST /api/v1/auth/logout` | Browser identity | Authenticated cookie session; CSRF | `browserLogout` |
| `POST /api/v1/auth/password/forgot` | Browser identity | CSRF; generic response; recovery throttle | `requestPasswordReset` |
| `POST /api/v1/auth/password/reset` | Browser identity | CSRF; one-time reset token; recovery throttle | `resetPassword` |
| `POST /api/v1/auth/email/verification-notification` | Browser identity | Active authenticated session | `sendEmailVerification` |
| `GET /api/v1/auth/email/verify/{id}/{hash}` | Browser identity | Signed, expiring URL; verification throttle | `verifyEmail` |
| `POST /api/v1/auth/mfa/totp/setup` | Browser identity | Active session; active security session; setup throttle | `browserMfaSetup` |
| `POST /api/v1/auth/mfa/totp/confirm` | Browser identity | Active session; TOTP challenge throttle | `browserMfaConfirm` |
| `POST /api/v1/auth/mfa/challenge` | Browser identity | Active session; TOTP/recovery challenge throttle | `browserMfaChallenge` |
| `POST /api/v1/mobile/auth/login` | Mobile identity | Device-bound credential throttle | `mobileLogin` |
| `POST /api/v1/mobile/auth/refresh` | Mobile identity | One-time device-bound refresh; rotation/reuse detection | `mobileRefresh` |
| `POST /api/v1/mobile/auth/logout` | Mobile identity | Opaque bearer plus device identifier | `mobileLogout` |
| `POST /api/v1/mobile/auth/mfa/totp/setup` | Mobile identity | Opaque bearer; setup throttle | `mobileMfaSetup` |
| `POST /api/v1/mobile/auth/mfa/totp/confirm` | Mobile identity | Opaque bearer; challenge throttle | `mobileMfaConfirm` |
| `POST /api/v1/mobile/auth/mfa/challenge` | Mobile identity | Opaque bearer; TOTP/recovery challenge throttle | `mobileMfaChallenge` |
| `GET /api/v1/user/me` | User | Session+CSRF or bearer; active account/session; verified email | `getCurrentUser` |
| `GET /api/v1/user/dashboard` | User | Same protected boundary; person-linked aggregate for member home | `getUserDashboard` |
| `GET /api/v1/user/capabilities` | User | Same protected boundary; capability snapshot for mobile guards | `getUserCapabilities` |
| `POST /api/v1/user/authorization/check` | User | Same protected boundary; alias-normalized permission check | `checkUserAuthorization` |
| `PUT /api/v1/user/preferences` | User | Same protected boundary; own Person only | `updateUserPreferences` |
| `GET /api/v1/user/consents` | User | Same protected boundary; own Person only | `listUserConsents` |
| `POST /api/v1/user/consents` | User | Protected boundary plus recent MFA; own Person only | `grantUserConsent` |
| `DELETE /api/v1/user/consents/{consent}` | User | Recent MFA; ownership-scoped lookup | `withdrawUserConsent` |
| `GET /api/v1/user/notifications` | User | Own user_id or person_id notifications; broadcast template title/body when present | `listUserNotifications` |
| `POST /api/v1/user/notifications/{notification}/read` | User | Ownership-scoped `read_at` stamp | `markUserNotificationRead` |
| `GET /api/v1/user/payments/intents` | User | Own `PaymentIntent` where payer_person_id matches | `listUserPaymentIntents` |
| `GET /api/v1/user/payments/transactions` | User | Own transactions via payer intents | `listUserPaymentTransactions` |
| `GET /api/v1/user/payments/receipts/{receipt}` | User | Own receipt via payer intent chain | `getUserPaymentReceipt` |
| `POST /api/v1/user/payments/giving-intents` | User | Recent MFA; `Idempotency-Key`; amount_minor+currency; governance via `PAYMENT_GOVERNANCE_MODE` (deny → 422 `PAYMENT_GOVERNANCE_DENIED`; allow_local/allow_configured returns intent + `client_payload`) | `createUserGivingIntent` |
| `POST /api/v1/user/payments/giving-intents/{intent}/complete` | User | Recent MFA; local_manual gateway only; creates transaction+receipt and marks succeeded | `completeUserGivingIntent` |
| `GET /api/v1/user/kca/dashboard` | User | Enrollment summary, open assignments, mentor, certificate pointer (read-only; OD-008 writes not exposed) | `getUserKcaDashboard` |
| `GET /api/v1/user/kca/modules` | User | Active curriculum modules | `listUserKcaModules` |
| `GET /api/v1/user/kca/modules/{module}` | User | Module + ordered lessons | `getUserKcaModule` |
| `GET /api/v1/user/kca/assignments` | User | Own enrollment assignments (non-draft) | `listUserKcaAssignments` |
| `GET /api/v1/user/kca/mentor` | User | Current mentor assignment for enrollment | `getUserKcaMentor` |
| `GET /api/v1/user/kca/attendance` | User | Own attendance sessions | `listUserKcaAttendance` |
| `GET/POST /api/v1/user/prayers` | User | Own `PrayerRequest` list/create | `listUserPrayers` / `createUserPrayer` |
| `GET/POST /api/v1/user/needs` | User | Own `PastoralNeed` list/create | `listUserNeeds` / `createUserNeed` |
| `GET/POST /api/v1/user/messages/conversations` | User | In-app conversations; create requires other participant_person_ids + first_message | `listUserMessageConversations` / `createUserMessageConversation` |
| `GET/POST /api/v1/user/messages/conversations/{conversation}/messages` | User | Participant-only message list/create | `listUserConversationMessages` / `createUserConversationMessage` |
| `GET/PUT /api/v1/user/sync/checkpoint` | User | Person sync cursor get/set | `getUserSyncCheckpoint` / `putUserSyncCheckpoint` |
| `GET /api/v1/user/sync/changes?since=` | User | Change feed `{changes:[{type,id,updated_at}], next_cursor}` for prayers/needs/notifications/payment intents after ISO8601 cursor | `listUserSyncChanges` |
| `GET /api/v1/content/pages` | Public / CMS | Published page summaries (slug, title, summary) | `listContentPages` |
| `GET /api/v1/content/pages/{slug}` | Public / CMS | Published page + published items ordered | `getContentPage` |
| `GET/POST /api/v1/admin/content/pages` | Admin / CMS | Recent MFA; `platform.configuration.manage` (temporary until `content.content.manage`) | `listAdminContentPages` / `createAdminContentPage` |
| `PUT /api/v1/admin/content/pages/{page}` | Admin / CMS | Same permission; ULID page | `updateAdminContentPage` |
| `POST /api/v1/admin/content/pages/{page}/items` | Admin / CMS | Same permission; add content item | `createAdminContentItem` |
| `GET /api/v1/user/security/devices` | User | Recent MFA; own devices only | `listUserDevices` |
| `DELETE /api/v1/user/security/devices/{device}` | User | Recent MFA; atomic device/session/token revocation | `revokeUserDevice` |
| `GET /api/v1/user/security/sessions` | User | Recent MFA; own sessions only | `listUserSessions` |
| `DELETE /api/v1/user/security/sessions/{securitySession}` | User | Recent MFA; ownership-scoped revocation | `revokeUserSession` |
| `GET /api/v1/admin/users` | Admin | Protected boundary, recent MFA, `identity.users.view`, requested scope | `listAdminUsers` |
| `GET /api/v1/admin/users/{user}` | Admin | Same; BOLA-safe scoped lookup | `getAdminUser` |
| `POST /api/v1/admin/users/{user}/suspension` | Admin | Recent MFA, `identity.users.suspend`, requested scope, no self-action | `suspendAdminUser` |
| `DELETE /api/v1/admin/users/{user}/suspension` | Admin | Recent MFA, `identity.users.reactivate`, requested scope, no self-action | `reactivateAdminUser` |
| `GET /api/v1/admin/access/roles` | Admin | Recent MFA, `identity.roles.view`, requested scope | `listAdminRoles` |
| `GET /api/v1/admin/access/permissions` | Admin | Recent MFA, `identity.permissions.view`, requested scope | `listAdminPermissions` |
| `GET /api/v1/admin/access/scope-assignments` | Admin | Recent MFA, `identity.scopes.view`, requested scope | `listAdminScopeAssignments` |
| `GET /api/v1/admin/organization/countries` | Admin | Recent MFA, `organization.countries.view`; collection constrained to global/country/unit-derived country | `listAdminCountries` |
| `POST /api/v1/admin/organization/countries` | Admin | Recent MFA, `organization.countries.manage`; global-only; audited `CreateCountryAction` | `createAdminCountry` |
| `GET /api/v1/admin/organization/countries/{country}/levels` | Admin | Recent MFA, `organization.countries.view`; country record policy | `listAdminAdministrativeLevels` |
| `POST /api/v1/admin/organization/countries/{country}/levels` | Admin | Recent MFA, `organization.countries.manage`; contained country; audited `CreateAdministrativeLevelAction` | `createAdminAdministrativeLevel` |
| `GET /api/v1/admin/organization/units` | Admin | Recent MFA, `organization.units.view`; global/country/administrative-unit subtree query | `listAdminAdministrativeUnits` |
| `POST /api/v1/admin/organization/units` | Admin | Recent MFA, `organization.units.manage`; parent/country containment; audited `CreateAdministrativeUnitAction` | `createAdminAdministrativeUnit` |
| `PATCH /api/v1/admin/organization/units/{unit}/parent` | Admin | Recent MFA, `organization.units.manage`; source and destination containment; cycle-safe audited move | `moveAdminAdministrativeUnit` |
| `GET /api/v1/admin/organization/locations` | Admin | Recent MFA, `organization.locations.view`; global/country/administrative-unit subtree query | `listAdminLocations` |
| `POST /api/v1/admin/organization/locations` | Admin | Recent MFA, `organization.locations.manage`; target containment; strict timezone/coordinates; audited | `createAdminLocation` |
| `GET /api/v1/admin/platform/configurations` | Admin | Recent MFA, `platform.configuration.view`; scoped collection; confidential values redacted | `listAdminPlatformConfigurations` |
| `PUT /api/v1/admin/platform/configurations` | Admin | Recent MFA, `platform.configuration.manage`; context containment/global policy; typed encrypted audited upsert | `upsertAdminPlatformConfiguration` |
| `GET /api/v1/admin/platform/feature-flags` | Admin | Recent MFA, `platform.feature_flags.view`; scoped collection | `listAdminFeatureFlags` |
| `PUT /api/v1/admin/platform/feature-flags` | Admin | Recent MFA, `platform.feature_flags.manage`; context containment; audited rollout/window upsert | `upsertAdminFeatureFlag` |
| `POST /api/v1/admin/platform/feature-flags/{featureFlag}/enabled` | Admin | Recent MFA, `platform.feature_flags.manage`; record-scope containment; audited enable | `enableAdminFeatureFlag` |
| `DELETE /api/v1/admin/platform/feature-flags/{featureFlag}/enabled` | Admin | Recent MFA, `platform.feature_flags.manage`; record-scope containment; audited disable | `disableAdminFeatureFlag` |
| `GET /api/v1/admin/platform/storage/object-storage` | Admin | Recent MFA, global `platform.storage.view`; credential-free status projection | `getAdminObjectStorage` |
| `PUT /api/v1/admin/platform/storage/object-storage` | Admin | Recent MFA, global `platform.storage.manage`; mutation throttle; write-only encrypted credentials; SSRF policy; audited | `configureAdminObjectStorage` |
| `POST /api/v1/admin/platform/storage/object-storage/validation` | Admin | Recent MFA, global `platform.storage.manage`; mutation throttle; live write/read/delete probe; audited | `validateAdminObjectStorage` |
| `POST /api/v1/admin/platform/storage/object-storage/activation` | Admin | Recent MFA, global `platform.storage.manage`; mutation throttle; successful probe required; audited | `activateAdminObjectStorage` |
| `DELETE /api/v1/admin/platform/storage/object-storage/activation` | Admin | Recent MFA, global `platform.storage.manage`; mutation throttle; returns new writes to local storage; audited | `deactivateAdminObjectStorage` |
| `GET /api/v1/admin/platform/maps` | Admin | Recent MFA, global `platform.maps.view`; credential-free status projection | `getAdminMapsProvider` |
| `PUT /api/v1/admin/platform/maps` | Admin | Recent MFA, global `platform.maps.manage`; mutation throttle; write-only encrypted keys; audited | `configureAdminMapsProvider` |
| `POST /api/v1/admin/platform/maps/activation` | Admin | Recent MFA, global `platform.maps.manage`; any one configured provider key activates that provider | `activateAdminMapsProvider` |
| `DELETE /api/v1/admin/platform/maps/activation` | Admin | Recent MFA, global `platform.maps.manage`; deactivates maps provider | `deactivateAdminMapsProvider` |
| `GET/POST /api/v1/admin/church/churches` | Admin / Church | Recent MFA; explicit view/manage permission; global/country/unit/church containment; minimized projection; audited create | `listAdminChurches` / `createAdminChurch` |
| `GET /api/v1/admin/church/home-churches` | Admin / Church | Recent MFA; explicit view permission; contained collection | `listAdminHomeChurches` |
| `GET /api/v1/admin/church/home-church-applications` | Admin / Church | Recent MFA; review permission; contact fields excluded | `listAdminHomeChurchApplications` |
| `POST /api/v1/admin/church/home-church-applications/{application}/transitions` | Admin / Church | Recent MFA; review permission; BOLA-safe Church containment; state machine and audit | `transitionAdminHomeChurchApplication` |
| `GET/POST /api/v1/admin/church/first-timers` | Admin / Church | Recent MFA; explicit view/manage permission; contained canonical Person workflow | `listAdminFirstTimers` / `createAdminFirstTimer` |
| `GET /api/v1/admin/church/follow-up-tasks` | Admin / Church | Recent MFA; view permission; contained collection | `listAdminChurchFollowUpTasks` |
| `POST /api/v1/admin/church/follow-up-tasks/{task}/completion` | Admin / Church | Recent MFA; completion permission; BOLA-safe containment; audited idempotent completion | `completeAdminChurchFollowUpTask` |
| `GET /api/v1/admin/mission/crusades` | Admin / Mission | Recent MFA; view permission; global/country/unit/crusade containment | `listAdminMissionCrusades` |
| `GET /api/v1/admin/mission/souls` | Admin / Mission | Recent MFA; soul-view permission; contained minimized collection | `listAdminMissionSouls` |
| `POST /api/v1/admin/mission/crusades/{crusade}/souls` | Admin / Mission | Recent MFA; capture permission; BOLA-safe containment; HMAC idempotency and audit | `captureAdminMissionSoul` |
| `POST /api/v1/admin/mission/souls/{soul}/mentor-assignment` | Admin / Mission | Recent MFA; assignment permission; active-team/state constraints and idempotency | `assignAdminMissionSoulMentor` |
| `POST /api/v1/admin/mission/souls/{soul}/follow-ups` | Admin / Mission | Recent MFA; record permission; mentor ownership/state checks and idempotency | `recordAdminMissionSoulFollowUp` |
| `POST /api/v1/admin/mission/souls/{soul}/follow-up-completion` | Admin / Mission | Recent MFA; completion permission; workflow eligibility and audit | `completeAdminMissionSoulFollowUp` |
| `GET /api/v1/admin/security/audit-events` | Admin / Security | Recent MFA; `security.audit.view`; global-only; immutable minimized records and allowlisted filters | `listAdminAuditEvents` |
| `GET /api/v1/admin/security/access-decisions` | Admin / Security | Recent MFA; `security.access_decisions.view`; global-only; immutable minimized records | `listAdminAccessDecisions` |
| `GET /api/v1/admin/catalog/{domain}/{resource}` | Admin / Remaining domains | Recent MFA; explicit `*.view` permission; **global-only**; allowlisted read catalogs for KCA/Press/Events/Finance/Communications/Reporting/Privacy/Files with minimized projections (no encrypted/internal columns) | `listAdminCatalog*` (24 operations) |

Identity tests cover registration/login/logout, generic credential errors, signed verification, password recovery, session persistence, mobile rotation/reuse, device binding/revocation, TOTP/recovery and replay denial. Protected User tests cover own-record preferences, consents, devices, sessions, recent-MFA gating and cross-user 404s. Admin tests cover authentication, MFA, permission, scope containment, BOLA denial, catalog projection and suspension policy.

All implemented API routes return the shared success envelope or a stable-code error envelope. Public routes are throttled; validation, authentication, authorization, not-found, method, rate-limit, service, and unexpected failures are normalized without exposing exception details.

## Authoritative contract and generated clients

- OpenAPI 3.1 source: `openapi/public-v1.openapi.json`.
- Identity/User/Admin OpenAPI 3.1 source: `openapi/protected-v1.openapi.json`.
- Deterministic generation configuration: `clients/public-client-generation.json`.
- Protected generation configuration: `clients/protected-client-generation.json`.
- Generated TypeScript client: `clients/typescript/src/public-api.ts`.
- Generated protected TypeScript client: `clients/typescript/src/protected-api.ts`.
- Generated Dart client: `clients/dart/lib/public_api.dart`.
- Generated protected Dart client: `clients/dart/lib/protected_api.dart`.
- Validate with `composer openapi:validate`; regenerate with `composer clients:generate`; verify committed outputs with `composer clients:check`.

The contracts, generation manifests, and generated clients cover all 16 public and all 98 registered identity/User/Admin operations. Contract tests compare them to live Laravel route discovery and fail on stale generated artifacts. The organization/platform/storage/maps operations use specific OpenAPI request/response schemas and typed TypeScript inputs/results.
