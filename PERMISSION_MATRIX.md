# Permission Matrix

Status: provisional naming catalogue derived from approved Next.js and Flutter route contracts. It is not yet seeded and does not define role bundles.

Authorization formula:

`allow = authenticated + session risk/MFA + permission + scope containment + record policy + workflow eligibility + data-classification rule`

## Canonical families

- Identity: `identity.user.*`, `identity.role.*`, `identity.permission.view`, `security.session.*`, `organization.scope.*`.
- Geography: `organization.*`, `church.hierarchy.view`, `home_church.hierarchy.view`.
- Church/Home Church: `church.*`, `home_church.*`, `people.*`, `prayer.*`, `counselling.case.*`, `testimony.*`.
- Mission: `mission.crusade.*`, `mission.invitation.*`, `mission.team.*`, `mission.soul.*`, `mission.mentor.*`, `mission.follow_up.*`, `mission.partner.*`, `mission.support.*`.
- KCA: `kca.application.*`, `kca.orientation.*`, `kca.student.*`, `kca.cohort.*`, `kca.year.*`, `kca.mentor.*`, `kca.lecturer.*`, `kca.module.*`, `kca.lesson.*`, `kca.attendance.*`, `kca.assignment.*`, `kca.evidence.*`, `kca.intervention.*`, `kca.assessment.*`, `kca.certificate.*`, `kca.alumni.*`.
- Press: `press.publication.*`, `press.author.*`, `press.manuscript.*`, `press.distribution.*`, `press.asset.*`, `press.translation.*`, `press.sales.*`, `press.analytics.*`.
- Finance: `finance.transaction.*`, `finance.payment.*`, `finance.reconciliation.*`, `finance.receipts.*`, `finance.refunds.*`, `finance.disputes.*`, `finance.providers.*`.
- Communications: `communications.notification.*`, `communications.broadcast.*`, `communications.audience.*`, `communications.email.*`, `communications.sms.*`, `communications.whatsapp.*`, `communications.push.*`, `communications.in-app.*`, `communications.template.*`, `communications.delivery.*`.
- Reporting/settings: `reports.*`, `settings.*`, `platform.configuration.*`.
- Restricted security/privacy: `security.audit.*`, `security.decision.*`, `security.classification.*`, `safeguarding.case.*`, `safeguarding.child.*`, `safeguarding.consent.*`, `pastoral.record.*`, `privacy.export.*`, `privacy.deletion.*`, `privacy.request.*`.

Exact action names must be normalized before seeding because the client artifacts contain singular/plural and underscore/hyphen variations. Role names must never be used as authorization shortcuts.
