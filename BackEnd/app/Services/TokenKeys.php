<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class TokenKeys
{
    public function sign(array $claims): string
    {
        return JWT::encode($claims, file_get_contents(config('afra.private_key')), 'RS256', config('afra.signing_kid'));
    }

    public function decode(string $token): object
    {
        $keys = [config('afra.signing_kid') => config('afra.public_key')] + config('afra.previous_public_keys', []);
        $verification = [];
        foreach ($keys as $kid => $path) {
            if (! is_string($kid) || ! is_string($path) || ! is_readable($path)) {
                throw new RuntimeException('JWT verification key configuration is invalid.');
            }
            $verification[$kid] = new Key(file_get_contents($path), 'RS256');
        }

        return JWT::decode($token, $verification);
    }
}
