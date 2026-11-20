<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TokenKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $claims = app(TokenKeys::class)->decode($request->bearerToken() ?? '');
        } catch (\Throwable $e) {
            abort(401, 'Invalid access token.');
        }
        foreach (['iss', 'aud', 'sub', 'sid', 'jti', 'iat', 'nbf', 'exp', 'auth_version', 'acl_version', 'token_use'] as $claim) {
            abort_unless(isset($claims->{$claim}), 401);
        }
        $challenge = ($claims->aud ?? null) === 'afra-password-change' && ($claims->token_use ?? null) === 'password_change' && $request->is('api/v1/me/password', 'api/auth/logout');
        abort_unless(($claims->iss ?? null) === config('afra.issuer') && ($challenge || (($claims->aud ?? null) === 'afra-internal' && ($claims->token_use ?? null) === 'access')), 401);
        $user = User::find($claims->sub ?? 0);
        $session = DB::table('auth_sessions')->where('id', $claims->sid ?? '')->first();
        abort_unless($user && (! $user->temporary_password_expires_at || now()->lt($user->temporary_password_expires_at)) && $user->active && (! $user->expires_at || now()->lt($user->expires_at)) && $session && ! $session->revoked_at && now()->lt($session->expires_at) && $session->user_id === $user->id && $user->auth_version === ($claims->auth_version ?? null), 401);
        abort_if($user->must_change_password && ! $request->is('api/v1/me/password', 'api/auth/logout'), 403, 'Password change required.');
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('session_id', $session->id);

        return $next($request);
    }
}
