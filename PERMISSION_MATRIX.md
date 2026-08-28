# Permission Matrix

Status: eight explicit unassigned bundles are installable for member security, identity/security administration, geography, platform settings/storage/files, Church operations, Mission operations, remaining-domain catalog reads, and remaining-domain mutation operations; some pastoral/partner/AI families remain provisional.

Authorization formula:

`allow = authenticated + session risk/MFA + permission + scope containment + record policy + workflow eligibility + data-classification rule`

## Canonical families

- Registered identity administration: `identity.users.view`, `identity.users.suspend`, `identity.users.reactivate`, `identity.roles.view`, `identity.roles.assign`, `identity.permissions.view`, `identity.permissions.grant`, `identity.scopes.view`, `identity.scopes.assign`.
- Member self-service: own-record preferences/consents plus recent-MFA device/session revocation; `mobile.app.access` for Flutter member navigation; transport ownership checks are mandatory and are not replaced by a broad wildcard.
- Registered geography Admin permissions: `organization.countries.view`, `organization.countries.manage`, `organization.units.view`, `organization.units.manage`, `organization.locations.view`, and `organization.locations.manage`; broader `church.hierarchy.view` and `home_church.hierarchy.view` remain provisional.
- Registered Church/Home Church permissions: `church.churches.view/manage`, `church.home_churches.view`, `church.home_church_applications.review/manage`, `church.memberships.manage`, `church.first_timers.view/manage`, and `church.follow_up.view/complete`; pastoral families remain provisional.
- Registered Mission permissions: `mission.crusades.view`, `mission.souls.view/capture`, `mission.mentors.assign`, `mission.follow_up.record/complete`, and `mission.invitations.manage/transition`; partner/support families remain provisional.
- Registered remaining-domain catalog permissions (global-only reads): `kca.applications/enrollments/evidence/assessments/certificates.view`, `kca.governance.view`, `press.publications/translations.view`, `events.events/registrations.view`, `finance.payment_*.view`, `communications.templates/audiences/broadcasts/deliveries/notifications.view`, `reporting.alert_rules/alert_occurrences.view`, `privacy.data_subject_requests.view`, and `platform.files.view`.
- Registered remaining-domain mutation permissions (global-only writes via `domain_operations_administrator`): KCA application/enrollment/assignment/evidence/certificate mutations plus KCA year/cohort/module/lesson/attendance writes, `kca.governance.manage`, and `kca.certificates.revoke`; Press publication/ISBN/translation mutations; Events create plus registration/attendance/feedback; Finance payment-intent/refund; Communications template/audience/broadcast/delivery/notification; Reporting alert-rule/occurrence; Privacy DSR/export lifecycle; Safeguarding incident/guardian. Broader alumni/sales/provider families remain provisional.
- Registered platform settings Admin permissions: `platform.configuration.view/manage`, `platform.feature_flags.view/manage`, `platform.storage.view/manage`, `platform.maps.view/manage`, `platform.payments.view/manage`, `platform.communications.view/manage`, `platform.files.manage/approve`, `platform.search.query`, and `platform.advisory.request`. Search queries local published-safe catalog records; advisory AI remains provider-disabled.
- Restricted security/privacy: registered `security.audit.view` and `security.access_decisions.view` are global-only; classification and broader pastoral privacy workflows remain provisional/default-deny.

Role names and wildcard permission patterns must never be used as authorization shortcuts. The explicit bundle installer is idempotent and never assigns an operational user.

Current evaluation uses an active, unexpired role assignment, explicit permission, contained scope, recent Admin MFA, record policy, and immutable decisions. Global contains approved organizational scopes; country/unit scopes now contain Church, Home Church and geolocated Mission crusades; own-record matches only the canonical User/Person. The installer provisions eight bundles and never assigns users.

Alert evaluation matches `condition_type` against the evaluation context and authenticated visibility is allowed because HTTP already requires permission plus scope. Safeguarding restricted-record reads, guardian/child communications, payment governance without a live provider, and outbound delivery remain default deny. Privacy export execution is allowed; deletion/anonymization remains denied. KCA certification is eligible only when every assignment is in final assessment. No operational administrator is seeded.
