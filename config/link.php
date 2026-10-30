<?php

return [

    /*
    | Timezone used to display dates when no organisation timezone applies.
    | Dates are always stored in UTC (config app.timezone).
    */
    'display_timezone' => env('LINK_DISPLAY_TIMEZONE', 'Africa/Niamey'),

    /*
    | File fields of application forms. Organisations may be stricter per
    | field, never looser than the caps. Sizes target slow mobile connections.
    */
    'uploads' => [
        'allowed_extensions' => ['pdf', 'jpg', 'png', 'docx'],
        'default_max_kb' => 5120,
        'max_kb_cap' => 10240,
        'default_max_files' => 3,
        'max_files_cap' => 10,
    ],

    'invitations' => [
        'validity_days' => 7,
    ],

    /*
    | Rate limits. Mobile operators in Niger put many users behind the same
    | public IP (carrier-grade NAT), so per-IP limits are deliberately loose
    | and paired with per-account limits.
    */
    'rate_limits' => [
        'login_per_minute' => 5,
        'register_per_hour_per_ip' => 20,
        'password_reset_per_hour' => 5,
        'organization_requests_per_hour_per_ip' => 5,
        'invitation_accept_per_minute' => 10,
    ],

    'anti_spam' => [
        // Minimum time, in seconds, between displaying a public form and submitting it.
        'minimum_fill_seconds' => (int) env('ANTI_SPAM_MIN_SECONDS', 3),
    ],

    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'timeout_seconds' => 5,
    ],

    'security' => [
        'csp_enabled' => (bool) env('CSP_ENABLED', true),
        'hsts_enabled' => (bool) env('HSTS_ENABLED', false),
    ],

];
