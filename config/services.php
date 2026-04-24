<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'notifications' => [
        'max_burst' => env('NOTIFICATION_MAX_BURST', 20),
        'push_batch_size' => env('NOTIFICATION_PUSH_BATCH_SIZE', 100),
        'push_batch_pacing_us' => env('NOTIFICATION_PUSH_BATCH_PACING_US', 100000),
        'max_alerts_per_day' => env('DONOR_MAX_ALERTS_PER_DAY', 3),
        'cooldown_hours' => env('DONOR_ALERT_COOLDOWN_HOURS', 12),
        'sms_retry_attempts' => env('SMS_RETRY_ATTEMPTS', 3),
        'sms_retry_delay_ms' => env('SMS_RETRY_DELAY_MS', 800),
        'pacing_us' => env('NOTIFICATION_PACING_US', 5000),
        'emergency_default_expiration_hours' => env('EMERGENCY_MODE_DEFAULT_EXPIRATION_HOURS', 0),
        'emergency_escalation_delay_minutes' => env('EMERGENCY_ESCALATION_DELAY_MINUTES', 2),
        'emergency_high_urgency_max_delay_minutes' => env('EMERGENCY_HIGH_URGENCY_MAX_DELAY_MINUTES', 5),
        'emergency_medium_urgency_max_delay_minutes' => env('EMERGENCY_MEDIUM_URGENCY_MAX_DELAY_MINUTES', 15),
        'emergency_low_urgency_max_delay_minutes' => env('EMERGENCY_LOW_URGENCY_MAX_DELAY_MINUTES', 30),
        'emergency_priority_boost_factor' => env('EMERGENCY_PRIORITY_BOOST_FACTOR', 0.15),
        'work_hours_start' => env('DONOR_WORK_HOURS_START', 8),
        'work_hours_end' => env('DONOR_WORK_HOURS_END', 17),
        'prediction_min_samples' => env('DONOR_AVAILABILITY_PREDICTION_MIN_SAMPLES', 3),
        'prediction_decline_rate' => env('DONOR_AVAILABILITY_PREDICTION_DECLINE_RATE', 0.7),
        'disaster_triggers' => ['earthquake', 'major accident', 'large-scale emergency'],
        'disaster_force_priority_requests' => env('DISASTER_FORCE_PRIORITY_REQUESTS', true),
        'disaster_expanded_radius_km' => env('DISASTER_EXPANDED_RADIUS_KM', 200),
    ],

    'monitoring' => [
        'metrics_token' => env('MONITORING_METRICS_TOKEN'),
    ],

    'geo_matching' => [
        'base_speed_kmph' => env('GEO_BASE_SPEED_KMPH', 40),
        'max_target_travel_minutes' => env('GEO_MAX_TARGET_TRAVEL_MINUTES', 120),
    ],

    'national_integrations' => [
        'timeout_seconds' => env('NATIONAL_INTEGRATION_TIMEOUT_SECONDS', 10),
        'partners' => [
            'philippine_red_cross' => [
                'label' => 'Philippine Red Cross',
                'enabled' => env('NATIONAL_PARTNER_PRC_ENABLED', false),
                'endpoint' => env('NATIONAL_PARTNER_PRC_ENDPOINT'),
                'token' => env('NATIONAL_PARTNER_PRC_TOKEN'),
                'scope' => 'national',
            ],
        ],
    ],

];
