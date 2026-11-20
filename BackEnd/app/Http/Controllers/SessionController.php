<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    private function browser(Request $request): void
    {
        abort_unless($request->header('X-Afra-Client') === 'spa', 403);
        $origin = $request->header('Origin');
        abort_unless($origin ? in_array($origin, config('afra.origins'), true) : $request->header('Sec-Fetch-Site') === 'same-origin', 403, 'Untrusted browser origin.');
    }

    public function csrf(Request $request): JsonResponse
    {
        $this->browser($request);
        $cookie = $request->cookie(config('afra.refresh_cookie'));
        if (! is_string($cookie) || $cookie === '') {
            return response()->json(['data' => null]);
        }
        $data = DB::transaction(function () use ($request, $cookie) {
            $session = DB::table('auth_sessions')->where('refresh_hash', hash('sha256', $cookie))->lockForUpdate()->first();
            if (! $session || $session->revoked_at || now()->gte($session->expires_at)) {
                return null;
            }
            abort_if($request->hasHeader('X-Afra-Session') && ! hash_equals($session->id, $request->header('X-Afra-Session')), 409, 'Browser session changed. Sign in again.');
            $user = User::find($session->user_id);
            if (! $user || ! $user->active || $user->must_change_password || ($user->expires_at && now()->gte($user->expires_at))) {
                return null;
            }
            $nonce = bin2hex(random_bytes(32));
            DB::table('auth_sessions')->where('id', $session->id)->update(['csrf_hash' => hash('sha256', $nonce)]);

            return ['csrf_token' => $nonce, 'session_id' => $session->id, 'user_id' => $user->id];
        });

        return response()->json(['data' => $data]);
    }

    public function end(Request $request): JsonResponse
    {
        $this->browser($request);
        $v = $request->validate(['expected_session_id' => 'required|uuid']);
        DB::transaction(function () use ($request, $v) {
            $session = DB::table('auth_sessions')->where('refresh_hash', hash('sha256', (string) $request->cookie(config('afra.refresh_cookie'))))->lockForUpdate()->first();
            abort_unless($session && $session->id === $v['expected_session_id'], 401);
            abort_unless(hash_equals($session->csrf_hash, hash('sha256', (string) $request->header('X-CSRF-Token'))), 403);
            DB::table('auth_sessions')->where('id', $session->id)->update(['revoked_at' => now()]);
        });

        return response()->json(['data' => true])->withoutCookie(config('afra.refresh_cookie'), '/api/auth');
    }
}
