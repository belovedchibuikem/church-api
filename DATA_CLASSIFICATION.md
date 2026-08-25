# Data Classification

| Class | Examples | Minimum handling |
| --- | --- | --- |
| Public | Published churches, events, publications, approved stories | Public projection; cache/SEO only under content policy. |
| Internal | Operational schedules, aggregate reports, non-sensitive configuration | Authenticated, scoped, no public cache. |
| Confidential | Member contact data, giving/payment details, KCA records | Need-to-know permission and scope, encrypted transport/storage as applicable, careful logging and export. |
| Restricted | Counselling, safeguarding, sensitive child data, pastoral records | Explicit restricted permission, record policy, enhanced access audit, no broad cache/search/analytics. |

Classification applies to fields as well as records. A public parent entity does not make every related field public. AI prompts, telemetry, exports, notifications, and search indexes must apply the same classification and minimization rules.

Person profile names and the User-to-Person association are confidential by default. A Person ULID is an opaque identifier, not permission to disclose the profile it references. Audit metadata must contain only fields approved for the event; secrets and restricted narrative content are prohibited.
