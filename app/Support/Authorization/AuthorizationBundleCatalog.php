<?php

namespace App\Support\Authorization;

final class AuthorizationBundleCatalog
{
    public const MEMBER_SECURITY_ROLE = 'member_security_self_service';

    public const PLATFORM_ADMINISTRATOR_ROLE = 'platform_administrator';

    public const ORGANIZATION_ADMINISTRATOR_ROLE = 'organization_administrator';

    public const PLATFORM_SETTINGS_ADMINISTRATOR_ROLE = 'platform_settings_administrator';

    public const CHURCH_OPERATIONS_ADMINISTRATOR_ROLE = 'church_operations_administrator';

    public const MISSION_OPERATIONS_ADMINISTRATOR_ROLE = 'mission_operations_administrator';

    public const DOMAIN_CATALOG_ADMINISTRATOR_ROLE = 'domain_catalog_administrator';

    public const DOMAIN_OPERATIONS_ADMINISTRATOR_ROLE = 'domain_operations_administrator';

    /** @var array<string, array{name: string, permissions: array<int, string>}> */
    public const BUNDLES = [
        self::MEMBER_SECURITY_ROLE => [
            'name' => 'Member security self-service',
            'permissions' => [
                'identity.security.sessions.view',
                'identity.security.sessions.revoke',
                'identity.security.devices.view',
                'identity.security.devices.revoke',
                'identity.security.mfa.manage',
                'identity.consents.manage',
                'identity.preferences.manage',
                'mobile.app.access',
            ],
        ],
        self::PLATFORM_ADMINISTRATOR_ROLE => [
            'name' => 'Platform identity and access administrator',
            'permissions' => [
                'identity.users.view',
                'identity.users.suspend',
                'identity.users.reactivate',
                'identity.roles.view',
                'identity.roles.assign',
                'identity.permissions.view',
                'identity.permissions.grant',
                'identity.scopes.view',
                'identity.scopes.assign',
                'security.audit.view',
                'security.access_decisions.view',
            ],
        ],
        self::ORGANIZATION_ADMINISTRATOR_ROLE => [
            'name' => 'Organization and geography administrator',
            'permissions' => [
                'organization.countries.view',
                'organization.countries.manage',
                'organization.units.view',
                'organization.units.manage',
                'organization.locations.view',
                'organization.locations.manage',
            ],
        ],
        self::PLATFORM_SETTINGS_ADMINISTRATOR_ROLE => [
            'name' => 'Platform settings administrator',
            'permissions' => [
                'platform.configuration.view',
                'platform.configuration.manage',
                'platform.feature_flags.view',
                'platform.feature_flags.manage',
                'platform.storage.view',
                'platform.storage.manage',
                'platform.maps.view',
                'platform.maps.manage',
                'platform.payments.view',
                'platform.payments.manage',
                'platform.communications.view',
                'platform.communications.manage',
                'platform.files.manage',
                'platform.files.approve',
                'platform.search.query',
                'platform.advisory.request',
            ],
        ],
        self::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE => [
            'name' => 'Church and Home Church operations administrator',
            'permissions' => [
                'church.churches.view',
                'church.churches.manage',
                'church.home_churches.view',
                'church.home_church_applications.review',
                'church.home_church_applications.manage',
                'church.memberships.manage',
                'church.first_timers.view',
                'church.first_timers.manage',
                'church.follow_up.view',
                'church.follow_up.complete',
            ],
        ],
        self::MISSION_OPERATIONS_ADMINISTRATOR_ROLE => [
            'name' => 'Mission operations administrator',
            'permissions' => [
                'mission.crusades.view',
                'mission.souls.view',
                'mission.souls.capture',
                'mission.mentors.assign',
                'mission.follow_up.record',
                'mission.follow_up.complete',
                'mission.invitations.transition',
                'mission.invitations.manage',
            ],
        ],
        self::DOMAIN_CATALOG_ADMINISTRATOR_ROLE => [
            'name' => 'Remaining domain catalog administrator',
            'permissions' => [
                'kca.applications.view',
                'kca.enrollments.view',
                'kca.evidence.view',
                'kca.assessments.view',
                'kca.certificates.view',
                'kca.governance.view',
                'press.publications.view',
                'press.translations.view',
                'events.events.view',
                'events.registrations.view',
                'finance.payment_intents.view',
                'finance.payment_transactions.view',
                'finance.payment_reconciliations.view',
                'finance.payment_receipts.view',
                'finance.payment_refunds.view',
                'finance.payment_disputes.view',
                'communications.templates.view',
                'communications.audiences.view',
                'communications.broadcasts.view',
                'communications.deliveries.view',
                'communications.notifications.view',
                'reporting.alert_rules.view',
                'reporting.alert_occurrences.view',
                'privacy.data_subject_requests.view',
                'platform.files.view',
            ],
        ],
        self::DOMAIN_OPERATIONS_ADMINISTRATOR_ROLE => [
            'name' => 'Remaining domain operations administrator',
            'permissions' => [
                'kca.applications.transition',
                'kca.enrollments.manage',
                'kca.assignments.transition',
                'kca.evidence.submit',
                'kca.evidence.review',
                'kca.certificates.issue',
                'kca.certificates.revoke',
                'kca.governance.manage',
                'kca.years.manage',
                'kca.cohorts.manage',
                'kca.modules.manage',
                'kca.lessons.manage',
                'kca.attendance.record',
                'press.publications.manage',
                'press.publications.transition',
                'press.publications.assign_isbn',
                'press.translations.manage',
                'press.translations.transition',
                'events.events.manage',
                'events.registrations.manage',
                'events.attendance.record',
                'events.feedback.record',
                'finance.payment_intents.create',
                'finance.payment_refunds.request',
                'communications.templates.manage',
                'communications.audiences.manage',
                'communications.broadcasts.prepare',
                'communications.broadcasts.resolve',
                'communications.deliveries.attempt',
                'communications.notifications.create',
                'reporting.alert_rules.manage',
                'reporting.alert_rules.evaluate',
                'reporting.alert_occurrences.acknowledge',
                'reporting.alert_occurrences.resolve',
                'privacy.data_subject_requests.submit',
                'privacy.data_exports.begin',
                'privacy.data_exports.complete',
                'privacy.data_exports.expire',
                'safeguarding.incidents.report',
                'safeguarding.guardians.register',
            ],
        ],
    ];

    /** @return array<int, string> */
    public function permissionCodes(): array
    {
        return collect(self::BUNDLES)
            ->pluck('permissions')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
