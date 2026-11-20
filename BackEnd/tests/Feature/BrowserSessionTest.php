<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BrowserSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $this->keys('primary');
        config(['afra.previous_public_keys' => [], 'afra.refresh_cookie' => 'afra_refresh']);
        $this->user = User::factory()->create(['password' => 'BrowserTesting123!']);
        $this->user->forceFill(['must_change_password' => false])->save();
    }

    private function keys(string $kid): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-browser-'.$kid.'-private.pem', $private);
        file_put_contents('/tmp/afra-browser-'.$kid.'-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.signing_kid' => $kid, 'afra.private_key' => '/tmp/afra-browser-'.$kid.'-private.pem', 'afra.public_key' => '/tmp/afra-browser-'.$kid.'-public.pem']);
    }

    private function signIn(): TestResponse
    {
        return $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'BrowserTesting123!'])->assertOk();
    }

    private function cookie(): void
    {
        $login = $this->signIn();
        $this->withCredentials()->withUnencryptedCookie('afra_refresh', $login->getCookie('afra_refresh', false)->getValue())->withHeaders(['Origin' => 'http://localhost:5173', 'X-Afra-Client' => 'spa']);
    }

    public function test_bootstrap_requires_trusted_browser_context_and_recovers_only_a_live_session(): void
    {
        $this->getJson('/api/auth/csrf')->assertForbidden();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'X-Afra-Client' => 'spa'])->getJson('/api/auth/csrf')->assertOk()->assertJsonPath('data', null)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->cookie();
        $nonce = $this->getJson('/api/auth/csrf')->assertOk()->assertJsonPath('data.user_id', $this->user->id)->json('data.csrf_token');
        $this->assertSame(64, strlen($nonce));
        $this->assertSame(hash('sha256', $nonce), DB::table('auth_sessions')->first()->csrf_hash);
        $this->withHeader('Origin', 'https://attacker.example')->getJson('/api/auth/csrf')->assertForbidden();
        $this->assertSame(hash('sha256', $nonce), DB::table('auth_sessions')->first()->csrf_hash);
    }

    public function test_refresh_cannot_switch_subject_or_session(): void
    {
        $this->cookie();
        $bootstrap = $this->getJson('/api/auth/csrf')->assertOk()->json('data');
        $before = DB::table('auth_sessions')->first()->refresh_hash;
        $this->withHeader('X-CSRF-Token', $bootstrap['csrf_token'])->postJson('/api/auth/refresh')->assertUnauthorized();
        $this->assertSame($before, DB::table('auth_sessions')->first()->refresh_hash);
        $this->withHeader('X-CSRF-Token', $bootstrap['csrf_token'])->postJson('/api/auth/refresh', ['expected_session_id' => (string) Str::uuid(), 'expected_user_id' => $this->user->id])->assertUnauthorized();
        $this->assertSame($before, DB::table('auth_sessions')->first()->refresh_hash);
        $this->postJson('/api/auth/refresh', ['expected_session_id' => $bootstrap['session_id'], 'expected_user_id' => $this->user->id])->assertOk();
        $this->assertNotSame($before, DB::table('auth_sessions')->first()->refresh_hash);
    }

    public function test_cookie_logout_revokes_family_even_with_an_invalid_access_token(): void
    {
        $this->cookie();
        $bootstrap = $this->getJson('/api/auth/csrf')->assertOk()->json('data');
        $this->withToken('expired-or-invalid-access-token')->withHeader('X-CSRF-Token', 'invalid')->postJson('/api/auth/end-session', ['expected_session_id' => $bootstrap['session_id']])->assertForbidden();
        $this->assertNull(DB::table('auth_sessions')->first()->revoked_at);
        $this->withHeader('X-CSRF-Token', $bootstrap['csrf_token'])->postJson('/api/auth/end-session', ['expected_session_id' => $bootstrap['session_id']])->assertOk();
        $this->assertNotNull(DB::table('auth_sessions')->first()->revoked_at);
    }

    public function test_key_rotation_accepts_configured_previous_key_until_it_is_removed(): void
    {
        $token = $this->signIn()->json('data.access_token');
        $previous = config('afra.public_key');
        $this->keys('next');
        config(['afra.previous_public_keys' => ['primary' => $previous]]);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $next = $this->signIn()->json('data.access_token');
        $this->withToken($next)->getJson('/api/v1/me')->assertOk();
        config(['afra.previous_public_keys' => []]);
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($next)->getJson('/api/v1/me')->assertOk();
    }

    public function test_cross_origin_login_is_rejected_without_creating_a_session(): void
    {
        $this->withHeader('Origin', 'https://attacker.example')->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'BrowserTesting123!'])->assertForbidden();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_malformed_login_email_is_a_validation_error(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['unexpected'], 'password' => 'BrowserTesting123!'])->assertUnprocessable();
        $this->assertDatabaseCount('auth_sessions', 0);
    }
}
