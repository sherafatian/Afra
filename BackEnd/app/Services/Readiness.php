<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Readiness
{
    /** Infrastructure readiness only; never a claim of product/security acceptance. */
    public function inspect(bool $production = false): array
    {
        $checks = [];
        foreach (['pdo_pgsql', 'openssl', 'mbstring', 'intl', 'fileinfo', 'sodium', 'zip'] as $extension) {
            $checks['php_'.$extension] = extension_loaded($extension);
        }
        $key = config('app.key');
        $key = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $checks['signing_kid'] = is_string(config('afra.signing_kid')) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', config('afra.signing_kid')) === 1;
        $checks['encryption_key'] = is_string($key) && Encrypter::supported($key, config('app.cipher'));
        try {
            DB::select('select 1');
            $checks['database'] = true;
            $required = array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), glob(database_path('migrations/*.php')));
            $ran = DB::table('migrations')->pluck('migration')->all();
            $checks['schema_current'] = ! array_diff($required, $ran);
        } catch (\Throwable) {
            $checks['database'] = false;
            $checks['schema_current'] = false;
        }
        try {
            $probe = 'afra:readiness:'.Str::uuid();
            Cache::put($probe, 'available', 10);
            $checks['shared_cache'] = Cache::get($probe) === 'available';
            Cache::forget($probe);
        } catch (\Throwable) {
            $checks['shared_cache'] = false;
        }
        try {
            $private = @file_get_contents(config('afra.private_key'));
            $public = @file_get_contents(config('afra.public_key'));
            $details = $private ? openssl_pkey_get_details(openssl_pkey_get_private($private)) : false;
            $checks['signing_keys'] = $details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 3072 && openssl_sign('Afra readiness', $signature, $private, OPENSSL_ALGO_SHA256) && openssl_verify('Afra readiness', $signature, $public, OPENSSL_ALGO_SHA256) === 1;
            foreach (config('afra.previous_public_keys') as $previous) {
                $checks['signing_keys'] = $checks['signing_keys'] && is_string($previous) && is_readable($previous);
            }
        } catch (\Throwable) {
            $checks['signing_keys'] = false;
        }
        if ($production) {
            $checks['production_environment'] = app()->isProduction();
            $checks['debug_disabled'] = config('app.debug') === false;
            $checks['https_url'] = str_starts_with(config('app.url'), 'https://');
            $checks['shared_cache_driver'] = in_array(config('cache.default'), ['redis', 'database'], true);
            $checks['shared_queue_driver'] = in_array(config('queue.default'), ['redis', 'database'], true);
            $checks['argon2id'] = config('hashing.driver') === 'argon2id' && in_array('argon2id', password_algos(), true);
            $origins = config('afra.origins');
            $checks['exact_https_origins'] = count($origins) > 0 && collect($origins)->every(function ($origin) {
                $parts = parse_url($origin);

                return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ! empty($parts['host']) && ! str_contains($origin, '*') && ! isset($parts['user']) && ! isset($parts['pass']) && empty($parts['query']) && empty($parts['fragment']) && empty($parts['path']);
            });
            $proxies = config('afra.trusted_proxies', []);
            $checks['bounded_proxy_trust'] = collect($proxies)->every(function ($entry) {
                [$address, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
                if (! filter_var($address, FILTER_VALIDATE_IP)) {
                    return false;
                }
                if ($prefix === null) {
                    return true;
                }
                $maximum = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;

                return ctype_digit($prefix) && (int) $prefix > 0 && (int) $prefix <= $maximum;
            });
        }

        return ['ready_for_requests' => ! in_array(false, $checks, true), 'checks' => $checks, 'release_acceptance' => 'incomplete'];
    }
}
