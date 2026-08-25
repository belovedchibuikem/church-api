# MySQL Backup and Restore Runbook

This runbook applies to the authoritative MySQL database. A backup is not accepted as recoverable until it has been restored into an isolated database and the verification queries pass.

## Production prerequisites

- Use a dedicated backup identity with the minimum MySQL privileges required by `mysqldump`.
- Supply credentials through a protected MySQL option file or the deployment secret manager. Never put a password in a command argument, repository file, CI log, or backup filename.
- Encrypt backups before they leave the database host, store them in a separate failure domain, restrict access, and apply an approved retention policy.
- Record the MySQL server version, application release, migration count, backup checksum, start/end time, size, and operator or automation identity.
- Alert when a scheduled backup is missing, fails, is unexpectedly small, or has not passed a restore drill within the approved recovery-test interval.

Retention, recovery point objective, recovery time objective, encryption-key custody, and legal-hold rules remain governed by OD-007 and OD-012.

## Create a logical backup

Run a transactionally consistent dump with the `mysqldump` binary from the same MySQL major version as the server:

```text
mysqldump --defaults-extra-file=<protected-client-file> \
  --single-transaction --routines --triggers --events \
  --set-gtid-purged=OFF --no-tablespaces \
  --result-file=<protected-output-path> <database-name>
```

Immediately calculate a SHA-256 checksum, encrypt the file, transfer it to the approved backup store, and verify the stored object checksum. A successful process exit without those checks is not a completed backup.

## Restore drill

1. Provision a new, isolated MySQL database. Never restore a drill over development, test, staging, or production data.
2. Verify the expected destination name before running any create, restore, or drop command.
3. Restore the dump into the isolated destination.
4. Run `php artisan migrate:status` against the restored destination using an isolated environment file.
5. Compare the source manifest with the restored database:
   - table count;
   - migration count and migration names;
   - storage engine (`InnoDB` for every application table);
   - row counts for critical tables;
   - foreign-key integrity checks;
   - application boot and readiness using only the isolated destination.
6. Record elapsed restore time and compare it with the approved recovery time objective.
7. Remove the isolated destination and securely dispose of the plaintext drill copy after evidence is retained.

## Local verification evidence

On 2026-08-25, MySQL 8.4.7 successfully dumped `family_house_connect` and restored it into an isolated database. The restored copy matched 13 application tables, 13 InnoDB tables, 8 migrations, and representative row counts. The isolated database was removed after verification. This proves the local procedure only; it does not validate production credentials, encryption, retention, remote storage, recovery objectives, or a production-sized dataset.

