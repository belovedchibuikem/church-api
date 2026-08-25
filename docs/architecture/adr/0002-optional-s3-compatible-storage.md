# ADR 0002: Optional S3-compatible object storage

Status: accepted for the foundation

## Decision

Local filesystem storage is the safe default. S3-compatible object storage is optional and is resolved on demand from an encrypted MySQL configuration only after a successful connection probe and explicit activation. S3 is not required for application boot, liveness, migrations, queues, or existing local writes.

Configuration changes create a new revision, clear prior validation, and deactivate S3. Activation performs a disposable write/read/delete probe and records only stable failure codes. Switching back to local affects new writes and retains the S3 configuration.

The administration HTTP contract is deferred until authentication, global permissions, recent MFA, endpoint/SSRF controls, throttling, normalized errors, and immutable audit storage exist. No placeholder or unauthenticated route is allowed.

## Consequences

- Application services use the provider-neutral object-storage resolver rather than reading environment variables or a globally cached runtime disk.
- Credentials are encrypted with the Laravel application key and hidden from serialization. Application-key rotation must retain previous keys until stored credentials are re-encrypted.
- Future media records must persist the provider/configuration identity and object key. Activating another provider never reinterprets or migrates existing objects.
- An active S3 write failure is an error; it must not silently duplicate the write to local storage.
- Live S3/MinIO integration verification remains an environment-specific release gate.
