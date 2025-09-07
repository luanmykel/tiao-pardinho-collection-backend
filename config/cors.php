<?php

return [
    'paths' => ['api/*', 'login', 'logout'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Background', 'Access-Control-Allow-Origin'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
