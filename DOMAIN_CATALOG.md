# Domain Catalog

| Domain | Canonical responsibility | Delivery state |
| --- | --- | --- |
| Identity & Access | Person, User, credentials, sessions/devices, MFA, roles, permissions, scopes, consent | foundation: identity linkage, account state, consent/preferences, security records, RBAC/scope assignments, and access decisions implemented; transports/policies gated |
| Geography & Organization | Countries, configurable administrative levels/units, locations, hierarchy, scope containment | hierarchy, locations, country/unit containment, and scoped audited Admin APIs implemented; domain-specific containment remains policy-gated |
| Church | Churches, Home Churches, memberships, first timers, follow-up, attendance, groups, discipleship, pastoral care | foundation: Church/Home Church, membership history, applications, first timers, and configured follow-up implemented; broader pastoral modules/APIs planned |
| Mission | Crusades, invitations, teams, canonical-person soul journeys, mentoring, follow-up, partners, support | foundation: invitations, teams, soul capture, mentors, follow-up history/completion, and Church connection implemented; broader modules/APIs planned |
| KCA | Applications, admissions, cohorts, learning, evidence, assessment, certificates, alumni | lifecycle foundation implemented; OD-008 policy, fees/signers, alumni, and APIs pending |
| Press | Authors, manuscripts, review workflow, publications, assets, translations, catalogue, distribution | publication/translation workflow foundation implemented; authority, distribution, catalogue/sales, and APIs pending |
| Events | Events, registration, tickets, attendance, feedback | foundation: events, free registration, attendance, and feedback implemented; ticket/payment/API integration pending |
| Finance | Giving/payment intents, immutable transactions, reconciliation, receipts, refunds, disputes | provider-neutral persistence/actions implemented; payment governance and webhook verification default deny pending OD-009 |
| Communication | Notifications, broadcasts, audiences, templates, delivery attempts, channel adapters | foundation implemented with consent/scope/guardian hooks and disabled outbound provider; APIs/provider policy pending |
| Content & Media | Public content, private files, provider-aware storage, signed access, scanning, derivatives | optional S3 core plus secured global Admin configuration/activation API and private quarantined provider-aware file assets implemented; scanner-backed delivery/derivatives planned |
| Reporting | Canonical metrics, dashboards, reports, alerts, exports | foundation: metric registry, alert lifecycle, and private export artifacts implemented; concrete queries/policies/APIs pending |
| Security & Safeguarding | Audit events, access decisions, restricted records, guardian/child controls, privacy requests | foundation: audit/access decisions, guardian/child relationships, encrypted incidents, and privacy requests implemented; restricted-read/privacy governance pending |
| Search | Public and authorized search projections with field-level filtering | provider contract, no-side-effect default, query validation, and classification filtering implemented; indexes/providers/APIs pending |
| AI | Provider-neutral advisory assistance with explicit governance boundaries | advisory boundary, context sanitization, use-case restrictions, human-decision requirement, and disabled provider implemented; OD-009/provider/API work pending |
| Platform Configuration | Typed environment/scope settings and controlled feature rollout | scoped audited Admin APIs implemented with confidential redaction; concrete keys/flags remain policy-owned |

Domains share canonical identifiers and services. They are bounded modules in one deployable Laravel application, not separate systems.
