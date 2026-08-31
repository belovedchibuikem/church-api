<?php

namespace App\Admin;

use App\Support\Authorization\AuthorizationBundleCatalog;

/**
 * Maps dashboard modules to registered authorization bundle permissions.
 * Dashboard routes must never require permission codes that are absent from
 * {@see AuthorizationBundleCatalog}.
 */
final class AdminDashboardPermissionCatalog
{
    /** @return array<int, string> */
    public static function acceptablePermissions(AdminDashboardModule $module): array
    {
        return match ($module) {
            AdminDashboardModule::Global => [
                'identity.users.view',
                'platform.configuration.view',
                'security.audit.view',
            ],
            AdminDashboardModule::Geography => [
                'organization.countries.view',
            ],
            AdminDashboardModule::HomeChurches => [
                'church.home_churches.view',
            ],
            AdminDashboardModule::Church => [
                'church.churches.view',
            ],
            AdminDashboardModule::People => [
                'church.churches.view',
            ],
            AdminDashboardModule::Kca => [
                'kca.enrollments.view',
                'kca.applications.view',
            ],
            AdminDashboardModule::Mission => [
                'mission.crusades.view',
            ],
            AdminDashboardModule::Press => [
                'press.publications.view',
            ],
            AdminDashboardModule::Finance => [
                'finance.payment_intents.view',
            ],
            AdminDashboardModule::Communications => [
                'communications.templates.view',
                'platform.communications.view',
            ],
            AdminDashboardModule::Reports => [
                'reporting.alert_rules.view',
                'church.churches.view',
                'organization.countries.view',
                'church.home_churches.view',
            ],
            AdminDashboardModule::Security => [
                'security.audit.view',
            ],
            AdminDashboardModule::Safeguarding => [
                'safeguarding.incidents.report',
            ],
        };
    }
}
