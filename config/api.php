<?php

return [
    'mobile' => [
        'access_ttl_seconds' => (int) env('MOBILE_ACCESS_TTL_SECONDS', 900),
        'refresh_ttl_seconds' => (int) env('MOBILE_REFRESH_TTL_SECONDS', 2592000),
    ],

    'mfa' => [
        'recent_seconds' => (int) env('MFA_RECENT_SECONDS', 43200),
    ],

    'browser' => [
        'password_reset_url' => env('BROWSER_PASSWORD_RESET_URL', env('APP_URL').'/reset-password'),
    ],

    'rate_limits' => [
        'public_per_minute' => (int) env('PUBLIC_API_RATE_LIMIT_PER_MINUTE', 120),
        'public_catalogue_per_minute' => (int) env('PUBLIC_CATALOGUE_RATE_LIMIT_PER_MINUTE', 90),
        'certificate_verification_per_minute' => (int) env('CERTIFICATE_VERIFICATION_RATE_LIMIT_PER_MINUTE', 15),
        'home_church_application_per_minute' => (int) env('HOME_CHURCH_APPLICATION_RATE_LIMIT_PER_MINUTE', 5),
        'home_church_application_per_contact_per_hour' => (int) env('HOME_CHURCH_APPLICATION_RATE_LIMIT_PER_CONTACT_PER_HOUR', 10),
        'mobile_login_per_minute' => (int) env('MOBILE_LOGIN_RATE_LIMIT_PER_MINUTE', 5),
        'mobile_login_per_email_per_minute' => (int) env('MOBILE_LOGIN_RATE_LIMIT_PER_EMAIL_PER_MINUTE', 5),
        'mobile_registration_per_hour' => (int) env('MOBILE_REGISTRATION_RATE_LIMIT_PER_HOUR', 5),
        'mobile_refresh_per_minute' => (int) env('MOBILE_REFRESH_RATE_LIMIT_PER_MINUTE', 10),

        'mfa_setup_per_hour' => (int) env('MFA_SETUP_RATE_LIMIT_PER_HOUR', 5),
        'mfa_challenge_per_minute' => (int) env('MFA_CHALLENGE_RATE_LIMIT_PER_MINUTE', 5),
        'browser_csrf_per_minute' => (int) env('BROWSER_CSRF_RATE_LIMIT_PER_MINUTE', 60),
        'browser_registration_per_hour' => (int) env('BROWSER_REGISTRATION_RATE_LIMIT_PER_HOUR', 5),
        'browser_login_per_minute' => (int) env('BROWSER_LOGIN_RATE_LIMIT_PER_MINUTE', 5),
        'browser_verification_per_minute' => (int) env('BROWSER_VERIFICATION_RATE_LIMIT_PER_MINUTE', 6),
        'browser_password_recovery_per_minute' => (int) env('BROWSER_PASSWORD_RECOVERY_RATE_LIMIT_PER_MINUTE', 5),
        'admin_storage_per_hour' => (int) env('ADMIN_STORAGE_RATE_LIMIT_PER_HOUR', 10),
    ],
];
