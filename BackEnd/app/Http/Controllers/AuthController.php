<?php

namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function response(User $user, string $sessionId, string $refresh, string $csrf): JsonResponse
    {
        $now = time();
        $token = JWT::encode(['iss' => config('afra.issuer'), 'aud' => $user->must_change_password ? 'afra-password-change' : 'afra-internal', 'sub' => (string) $user->id, 'sid' => $sessionId, 'jti' => (string) Str::uuid(), 'iat' => $now, 'nbf' => $now, 'exp' => $now + ($user->must_change_password ? 300 : 900), 'auth_version' => $user->auth_version, 'acl_version' => $user->acl_version, 'token_use' => $user->must_change_password ? 'password_change' : 'access'], file_get_contents(config('afra.private_key')), 'RS256', 'primary');

        $response = response()->json(['data' => ['access_token' => $token, 'csrf_token' => $csrf, 'user' => $user->only(['id', 'name', 'email', 'is_system_admin', 'must_change_password'])]]);
        if ($user->must_change_password) {
            return $response->withoutCookie('afra_refresh', '/api/auth');
        }

        return $response->cookie('afra_refresh', $refresh, 10080, '/api/auth', null, app()->isProduction(), true, false, 'strict');
    }

    public function login(Request $r): JsonResponse
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string|max:128']);
        $u = User::where('email', mb_strtolower($v['email']))->first();
        $dummyHash = config('hashing.driver') === 'argon2id' ? '$argon2id$v=19$m=65536,t=4,p=1$vLvifhBofgFByhowKOyIbA$kepkHXpsL0px1D+utE6g7yRkh8BK0CaYIDacukskMy8' : '$2y$12$1ZzK2MY4jz9DY0qzRbxJ/eA16lj/V6CnDviB3Yb4t2rS.lPge44r6';
        $valid = Hash::check($v['password'], $u?->password ?? $dummyHash);
        abort_unless($u && $valid && $u->active && (! $u->expires_at || now()->lt($u->expires_at)), 401, 'Invalid credentials.');
        $sid = (string) Str::uuid();
        $refresh = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));
        DB::table('auth_sessions')->insert(['id' => $sid, 'user_id' => $u->id, 'refresh_hash' => hash('sha256', $refresh), 'csrf_hash' => hash('sha256', $csrf), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);

        return $this->response($u, $sid, $refresh, $csrf);
    }

    public function refresh(Request $r): JsonResponse
    {
        abort_unless(in_array($r->header('Origin'), config('afra.origins'), true), 403);
        $hash = hash('sha256', $r->cookie('afra_refresh', ''));
        $refresh = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));
        $session = DB::transaction(function () use ($r, $hash, $refresh, $csrf) {
            $spent = DB::table('spent_refresh_tokens')->where('hash', $hash)->first();
            if ($spent) {
                DB::table('auth_sessions')->where('id', $spent->session_id)->update(['revoked_at' => now()]);

                return null;
            }
            $s = DB::table('auth_sessions')->where(function ($q) use ($hash) {
                $q->where('refresh_hash', $hash)->orWhere('previous_hash', $hash);
            })->lockForUpdate()->first();
            if (! $s || $s->revoked_at || now()->gte($s->expires_at)) {
                return null;
            }
            if ($s->previous_hash === $hash) {
                DB::table('auth_sessions')->where('id', $s->id)->update(['revoked_at' => now()]);

                return null;
            }
            if (! hash_equals($s->csrf_hash, hash('sha256', $r->header('X-CSRF-Token', '')))) {
                return null;
            }
            DB::table('spent_refresh_tokens')->insert(['hash' => $hash, 'session_id' => $s->id, 'created_at' => now()]);
            DB::table('auth_sessions')->where('id', $s->id)->update(['previous_hash' => $hash, 'refresh_hash' => hash('sha256', $refresh), 'csrf_hash' => hash('sha256', $csrf), 'updated_at' => now()]);

            return $s;
        });
        abort_unless($session, 401);
        $u = User::findOrFail($session->user_id);
        abort_unless($u->active && (! $u->expires_at || now()->lt($u->expires_at)), 401);

        return $this->response($u, $session->id, $refresh, $csrf);
    }

    public function logout(Request $r): JsonResponse
    {
        DB::table('auth_sessions')->where('id', $r->attributes->get('session_id'))->update(['revoked_at' => now()]);

        return response()->json(['data' => true])->withoutCookie('afra_refresh', '/api/auth');
    }

    public function password(Request $r): JsonResponse
    {
        $v = $r->validate(['current_password' => 'required|string', 'password' => 'required|string|min:12|max:128|confirmed']);
        $u = $r->user();
        abort_unless(Hash::check($v['current_password'], $u->password), 422, 'Current password is incorrect.');
        abort_if(Hash::check($v['password'], $u->password), 422, 'Choose a different password.');
        DB::transaction(function () use ($u, $v) {
            $u->forceFill(['password' => Hash::make($v['password']), 'must_change_password' => false, 'auth_version' => $u->auth_version + 1])->save();
            DB::table('auth_sessions')->where('user_id', $u->id)->update(['revoked_at' => now()]);
        });

        return response()->json(['data' => true]);
    }
}
