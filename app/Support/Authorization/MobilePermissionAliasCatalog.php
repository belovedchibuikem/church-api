<?php

namespace App\Support\Authorization;

/**
 * Maps Flutter client permission strings to registered Laravel permission codes.
 */
final class MobilePermissionAliasCatalog
{
    public const MOBILE_APP_ACCESS = 'mobile.app.access';

    /** @var array<string, string> */
    public const ALIASES = [
        // Member self-service (registered)
        'profile.view' => 'identity.preferences.manage',
        'settings.view' => 'identity.preferences.manage',
        'settings.notifications.manage' => 'identity.preferences.manage',
        'settings.communications.manage' => 'identity.preferences.manage',
        'security.sessions.manage' => 'identity.security.sessions.view',
        'consents.manage_own' => 'identity.consents.manage',
        'privacy.manage_own' => 'identity.consents.manage',
        'privacy.export_own' => 'identity.consents.manage',
        'privacy.delete_own' => 'identity.consents.manage',

        // Church / home-church ops (registered families)
        'church.reports.view' => 'church.churches.view',
        'church.followup.view' => 'church.follow_up.view',
        'church.attendance.manage' => 'church.churches.manage',
        'church.activities.manage' => 'church.churches.manage',
        'home_church.members.view' => 'church.home_churches.view',
        'home_church.attendance.manage' => 'church.home_churches.view',
        'home_church.activities.manage' => 'church.home_churches.view',
        'home_church.reports.view' => 'church.home_churches.view',
        'home_church.reports.create' => 'church.home_churches.view',
        'home_church.needs.manage' => 'church.home_churches.view',
        'leadership.dashboard.view' => 'church.churches.view',
        'altar_call.followup.view' => 'church.follow_up.view',

        // Mission ops
        'mission.dashboard.view' => 'mission.crusades.view',
        'mission.souls.create' => 'mission.souls.capture',
        'mission.souls.view' => 'mission.souls.view',
        'mission.mentors.assign' => 'mission.mentors.assign',
        'mission.assignments.view' => 'mission.crusades.view',

        // KCA catalog reads / staff
        'kca.dashboard.view' => 'kca.enrollments.view',
        'kca.evidence.review' => 'kca.evidence.view',
        'kca.certification.view_own' => 'kca.certificates.view',
        'kca.admission.view_own' => 'kca.applications.view',
        'kca.attendance.view' => 'kca.enrollments.view',
        'kca.mentoring.view' => 'kca.enrollments.view',
        'kca.assessment.view_own' => 'kca.assessments.view',
        'kca.assessments.view_own' => 'kca.assessments.view',
        'kca.certificate.view_own' => 'kca.certificates.view',
        'kca.reviews.manage' => 'kca.applications.view',
        'kca.admission.manage' => 'kca.applications.view',
        'kca.lessons.deliver' => 'kca.enrollments.view',
        'kca.mentoring.intervene' => 'kca.enrollments.view',

        // Church/home-church finance workspaces (staff catalog)
        'church.finance.view' => 'finance.payment_intents.view',
        'home_church.finance.view' => 'finance.payment_intents.view',

        // Member self-service giving/history uses signed-in /user/payments
        // routes, not the admin finance catalog permissions.
        'giving.history.view' => self::MOBILE_APP_ACCESS,
        'payments.receipts.view_own' => self::MOBILE_APP_ACCESS,
        'payments.history.view_own' => self::MOBILE_APP_ACCESS,
        'payments.transactions.view_own' => self::MOBILE_APP_ACCESS,
        'payments.refunds.view_own' => 'finance.payment_refunds.view',
        'payments.disputes.view_own' => 'finance.payment_disputes.view',

        // Notifications catalog
        'notifications.view' => 'communications.notifications.view',
    ];

    public function canonicalize(string $clientPermission): string
    {
        if (isset(self::ALIASES[$clientPermission])) {
            return self::ALIASES[$clientPermission];
        }

        // Remaining Flutter UI permissions are provisional member navigation codes.
        return self::MOBILE_APP_ACCESS;
    }
}
