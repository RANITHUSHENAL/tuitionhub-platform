<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guardian invitation codes (contract §9.1–§9.2; D-015 Q29; D-023)
    |--------------------------------------------------------------------------
    |
    | Dedicated HMAC-SHA256 key for guardian_invitations.code_hash. Never committed or printed.
    | Use "base64:" followed by 32+ random bytes. Missing or too short: invitation create and
    | accept return 503; nothing else is affected. Rotating it invalidates every pending code.
    |
    */

    'hmac_key' => env('GUARDIAN_INVITE_HMAC_KEY'),

    'ttl_hours' => 48,

    // E2-7: every accept attempt counts, per user.
    'accept_attempts_per_15_minutes' => 5,

];
