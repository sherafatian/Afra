<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-account-private.pem', $private);
        file_put_contents('/tmp/afra-account-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-account-private.pem', 'afra.public_key' => '/tmp/afra-account-public.pem']);
    }

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create(['password' => 'AccountTesting123!']);
        $user->forceFill(['is_system_admin' => $admin, 'must_change_password' => false])->save();

        return $user;
    }

    private function token(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'AccountTesting123!'])->assertOk()->json('data.access_token');
    }

    public function test_only_admin_can_create_accounts_and_no_project_access_is_implicit(): void
    {
        $regular = $this->token($this->account());
        $this->withToken($regular)->getJson('/api/v1/system/users')->assertForbidden();
        $admin = $this->token($this->account(true));
        $values = ['name' => 'Training Tester', 'email' => 'TRAINING@example.org', 'password' => 'AccountTesting123!', 'reason' => 'Provision a training tester account.'];
        $this->withToken($regular)->postJson('/api/v1/system/users', $values)->assertForbidden();
        $created = $this->withToken($admin)->postJson('/api/v1/system/users', $values)->assertCreated()->assertJsonPath('data.active', true)->assertJsonPath('data.email', 'training@example.org')->assertJsonPath('data.must_change_password', true)->json('data.id');
        $this->assertDatabaseMissing('project_members', ['user_id' => $created]);
        $audit = DB::table('audit_events')->where('action', 'users.create')->first();
        $this->assertStringNotContainsString('AccountTesting123!', $audit->details);
        $this->assertArrayNotHasKey('password', json_decode($audit->details, true)['after']);
        $this->withToken($admin)->getJson('/api/v1/system/users?q=TRAINING&active=1')->assertOk()->assertJsonPath('data.total', 1);
        $this->withToken($admin)->postJson('/api/v1/system/users', $values + ['auth_version' => 99])->assertUnprocessable();
    }

    public function test_expiry_is_inclusive_tehran_day_and_edits_are_versioned(): void
    {
        $admin = $this->token($this->account(true));
        $user = $this->account();
        $old = $this->token($user);
        $day = now()->timezone('Asia/Tehran')->toDateString();
        $values = ['expires_on' => $day, 'reason' => 'Limit this account through today.'];
        $url = '/api/v1/system/users/'.$user->id;
        $this->withToken($admin)->patchJson($url, $values)->assertConflict();
        $this->withToken($admin)->patchJson($url, $values, ['If-Match' => '1'])->assertOk()->assertJsonPath('data.expires_on', $day)->assertJsonPath('data.account_version', 2);
        $this->assertSame(now()->timezone('Asia/Tehran')->addDay()->startOfDay()->utc()->timestamp, $user->fresh()->expires_at->timestamp);
        $this->withToken($old)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($admin)->patchJson($url, ['active' => false, 'reason' => 'Deactivate the training account.'], ['If-Match' => '1'])->assertConflict();
        $this->withToken($admin)->patchJson($url, ['expires_on' => null, 'reason' => 'Remove the training expiry limit.'], ['If-Match' => '2'])->assertOk()->assertJsonPath('data.expires_on', null);
    }

    public function test_reset_is_versioned_revokes_sessions_and_expires_after_24_hours(): void
    {
        $admin = $this->token($this->account(true));
        $user = $this->account();
        $old = $this->token($user);
        $url = '/api/v1/system/users/'.$user->id.'/reset-password';
        $values = ['password' => 'TemporaryTesting123!', 'reason' => 'Reset requested by training user.'];
        $this->withToken($admin)->postJson($url, $values)->assertConflict();
        $this->withToken($admin)->postJson($url, $values, ['If-Match' => '1'])->assertOk();
        $this->assertTrue($user->fresh()->must_change_password);
        $this->withToken($old)->getJson('/api/v1/me')->assertUnauthorized();
        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $values['password']])->assertOk()->json('data.access_token');
        $this->withToken($challenge)->getJson('/api/v1/projects')->assertUnauthorized();
        $this->travel(25)->hours();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $values['password']])->assertUnauthorized();
        $this->withToken($challenge)->postJson('/api/v1/me/password', ['current_password' => $values['password'], 'password' => 'FinalTestingPassword123!', 'password_confirmation' => 'FinalTestingPassword123!'])->assertUnauthorized();
    }

    public function test_temporary_password_change_clears_expiry_and_never_allows_self_lockout(): void
    {
        $adminUser = $this->account(true);
        $admin = $this->token($adminUser);
        $user = $this->account();
        $this->withToken($admin)->patchJson('/api/v1/system/users/'.$adminUser->id, ['active' => false, 'reason' => 'Attempt to deactivate own account.'], ['If-Match' => '1'])->assertConflict();
        $this->withToken($admin)->postJson('/api/v1/system/users/'.$user->id.'/reset-password', ['password' => 'TemporaryTesting123!', 'reason' => 'Reset the training password now.'], ['If-Match' => '1'])->assertOk();
        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'TemporaryTesting123!'])->assertOk()->json('data.access_token');
        $this->withToken($challenge)->postJson('/api/v1/me/password', ['current_password' => 'TemporaryTesting123!', 'password' => 'FinalTestingPassword123!', 'password_confirmation' => 'FinalTestingPassword123!'])->assertOk();
        $this->assertNull($user->fresh()->temporary_password_expires_at);
        $this->assertSame(3, $user->fresh()->account_version);
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
