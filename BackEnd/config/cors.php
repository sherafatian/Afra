<?php

return ['paths' => ['api/*'], 'allowed_methods' => ['*'], 'allowed_origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))), 'allowed_origins_patterns' => [], 'allowed_headers' => ['Accept', 'Content-Type', 'Authorization', 'X-CSRF-Token', 'If-Match', 'Idempotency-Key', 'X-Afra-Client', 'X-Afra-Session'], 'exposed_headers' => ['X-Request-ID'], 'max_age' => 600, 'supports_credentials' => true];
