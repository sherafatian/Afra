<?php

return [
    'issuer' => env('JWT_ISSUER', 'afra'),
    'private_key' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/private.pem')),
    'public_key' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/public.pem')),
    'origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173'))),
];
