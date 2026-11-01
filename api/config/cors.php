<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | TuitionHub (D-019): only the React SPA may call the API with credentials.
    | The origin is exact (scheme, host and port) and uses localhost, never 127.0.0.1.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-XSRF-TOKEN', 'X-Requested-With'],

    // The SPA reads Retry-After on 429s (OTP cooldown, rate limits; contract §1.4).
    'exposed_headers' => ['Retry-After'],

    'max_age' => 0,

    'supports_credentials' => true,

];
