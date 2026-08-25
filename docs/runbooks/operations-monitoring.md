# Operations Monitoring Runbook

Monitoring must use the application's safe health contracts and infrastructure-native metrics. It must not scrape exception messages, credentials, SQL, restricted records, or request bodies.

## HTTP probes

- `GET /api/v1/health` is the liveness probe. Restart an instance only after repeated liveness failures.
- `GET /api/v1/health/readiness` checks the configured database, cache, and queue dependencies. Remove an instance from service while it returns `503`.
- `GET /up` is Laravel's framework health route and is suitable for an internal platform probe.

The public response deliberately exposes only `ok` or `failed` component states. Correlate an incident through `X-Correlation-ID` and structured server logs rather than adding internal connection details to the response.

## Minimum signals and alerts

- HTTP request rate, latency percentiles, `4xx`/`5xx` rate, and readiness failures.
- MySQL availability, connection saturation, slow queries, replication or high-availability state, disk capacity, and backup/restore age.
- Redis availability, memory pressure, evictions, rejected connections, command latency, and persistence state when Redis is enabled.
- Queue depth and oldest-job age per queue, failed jobs, retry rate, worker count, worker restarts, and processing duration.
- Scheduler heartbeat and last successful execution for every critical scheduled task.
- PHP process saturation, memory, CPU, disk, certificate expiry, and deployment/version identifiers.

Page immediately for sustained readiness failure, unavailable database, missing workers, scheduler heartbeat loss, backup failure, or a growing oldest-job age. Warning thresholds must be calibrated from production traffic and recovery objectives rather than copied from local development.

## Worker and scheduler supervision

- Run queue workers under the deployment platform's process supervisor and restart them during deployment with `php artisan queue:restart`.
- Run exactly one scheduler trigger per minute with `php artisan schedule:run`; application tasks use overlap and single-server safeguards where required.
- Treat supervisor state and scheduler heartbeat as external monitoring responsibilities. The Laravel process cannot reliably prove that its own missing worker or cron trigger exists.
- Review failed jobs before retrying. Fix non-idempotent behavior before bulk retry, and never retry payment or privileged workflow jobs blindly.

## Incident evidence

Capture the UTC time window, release identifier, affected surface, correlation IDs, safe structured logs, queue/scheduler state, and relevant infrastructure metrics. Access to confidential or restricted telemetry follows the same permission, scope, retention, and audit rules as the source data.

The production observability vendor and notification routes remain open under OD-009 and OD-012. Provider selection must not change these signal and privacy requirements.
