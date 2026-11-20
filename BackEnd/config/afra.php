<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'signing_kid' => env('JWT_SIGNING_KID', 'primary'),
    'previous_public_keys' => json_decode(env('JWT_PREVIOUS_PUBLIC_KEYS', '{}'), true, 512, JSON_THROW_ON_ERROR),
    'refresh_cookie' => env('APP_ENV') === 'production' ? '__Secure-afra_refresh' : 'afra_refresh',
    'issuer' => env('JWT_ISSUER', 'afra'),
    'private_key' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/private.pem')),
    'public_key' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/public.pem')),
    'origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))),
];
