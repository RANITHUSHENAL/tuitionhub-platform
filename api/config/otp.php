<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP driver
    |--------------------------------------------------------------------------
    |
    | "log" writes codes to the local `otp` log channel (local development only).
    | "fake" keeps codes in memory (automated tests only).
    | Both refuse to run in production; there is no real SMS driver in the sprint.
    |
    */

    'driver' => env('OTP_DRIVER', 'log'),

    /*
    | HMAC-SHA256 key for code_hash and request_ip_hash (D-019). Never committed or printed.
    | Use "base64:" followed by 32+ random bytes. Missing or too short: OTP endpoints return 503.
    */
    'hmac_key' => env('OTP_HMAC_KEY'),

    'ttl_seconds' => 300,

    'resend_cooldown_seconds' => 60,

    'max_attempts' => 5,

    'limits' => [
        'request_per_phone_per_15_minutes' => 3,
        'request_per_phone_per_day' => 10,
        'request_per_ip_per_hour' => 10,
        'verify_per_ip_per_10_minutes' => 10,
    ],

];
