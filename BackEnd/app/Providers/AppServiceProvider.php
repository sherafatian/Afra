<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TrustProxies::at(config('afra.trusted_proxies', []));
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        RateLimiter::for('afra-probe', fn (Request $request) => Limit::perMinute(60)->by('probe:'.$request->ip()));
        RateLimiter::for('afra-login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', mb_strtolower(is_string($request->input('email')) ? $request->input('email') : 'invalid-input'))),
        ]);
        RateLimiter::for('afra-refresh', fn (Request $request) => Limit::perMinute(30)->by('refresh:'.$request->ip()));
        RateLimiter::for('afra-api', fn (Request $request) => Limit::perMinute(120)->by('api:'.($request->user()?->id ?? $request->ip())));
    }
}
