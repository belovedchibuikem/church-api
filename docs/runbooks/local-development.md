# Local Development Runbook

## Requirements

- PHP 8.3+
- Composer 2
- MySQL and Redis for infrastructure integration. PHPUnit uses a dedicated MySQL schema while array stores isolate non-database infrastructure.

## Setup

1. Copy `.env.example` to `.env` and set local secrets outside source control.
2. Run `composer install`.
3. Run `php artisan key:generate`.
4. Create the `family_house_connect` and `family_house_connect_testing` MySQL databases. Never point PHPUnit at development or production data.
5. Run `php artisan migrate` after connection validation. The application pins `DB_ENGINE=InnoDB` so it does not inherit an unsafe or incompatible server default.
6. Start workers with `php artisan queue:work` when queued features exist.
7. Run `php artisan test --compact` and `vendor/bin/pint --format agent` before handoff.

## Current probes

- Application status: `GET /api/v1`
- Liveness: `GET /api/v1/health`
- Dependency readiness: `GET /api/v1/health/readiness`
- Laravel framework health: `GET /up`

The liveness route proves only that the process can respond. Readiness probes the configured database, cache, and queue dependencies and reports only safe component statuses. It does not validate optional S3, mail, or other providers, and Redis is not covered until Redis is configured.

## Optional S3-compatible storage

Local storage is the default and requires no S3 environment variables. The application can persist an encrypted S3-compatible connection, verify it with a disposable write/read/delete probe, activate it for new writes, and switch back to local storage without deleting the saved configuration.

No storage administration route is registered yet. Wire the existing storage actions into the admin API only after authentication, global storage permissions, recent MFA, endpoint/SSRF validation, throttling, and secret-safe audit events are implemented. Never seed real object-storage credentials.
