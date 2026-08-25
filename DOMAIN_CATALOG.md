# Domain Catalog

| Domain | Canonical responsibility | Delivery state |
| --- | --- | --- |
| Identity & Access | Person, User, credentials, sessions/devices, MFA, roles, permissions, scopes, consent | foundation: canonical Person/Profile and audited User linkage implemented |
| Geography & Organization | Countries, configurable administrative levels/units, locations, hierarchy, scope containment | planned |
| Church | Churches, Home Churches, Online Church, memberships, attendance, groups, first timers, discipleship, pastoral care | planned |
| Mission | Crusades, invitations, teams, canonical-person soul journeys, mentoring, follow-up, partners, support | planned |
| KCA | Applications, admissions, cohorts, learning, evidence, assessment, certificates, alumni | planned |
| Press | Authors, manuscripts, review workflow, publications, assets, translations, catalogue, distribution | planned |
| Events | Events, registration, tickets, attendance, feedback | planned |
| Finance | Giving/payment intents, immutable transactions, reconciliation, receipts, refunds, disputes | planned |
| Communication | Notifications, broadcasts, audiences, templates, delivery attempts, channel adapters | planned |
| Content & Media | Public content, private files, provider-aware storage, signed access, scanning, derivatives | foundation: optional S3 connection core implemented; media workflows planned |
| Reporting | Canonical metrics, dashboards, reports, alerts, exports | planned |
| Security & Safeguarding | Audit events, access decisions, restricted records, guardian/child controls, privacy requests | foundation: append-only audit writer implemented; remaining controls planned |
| Search | Public and authorized search projections with field-level filtering | planned |
| AI | Provider-neutral advisory assistance with explicit governance boundaries | planned |

Domains share canonical identifiers and services. They are bounded modules in one deployable Laravel application, not separate systems.
