<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-test-private.pem', $private);
        file_put_contents('/tmp/afra-test-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-test-private.pem', 'afra.public_key' => '/tmp/afra-test-public.pem']);
    }

    private function account(bool $admin = false): User
    {
        $u = User::factory()->create(['password' => 'PasswordForTesting123!']);
        $u->forceFill(['is_system_admin' => $admin, 'must_change_password' => false])->save();

        return $u;
    }

    private function token(User $u): string
    {
        return $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'PasswordForTesting123!'])->assertOk()->json('data.access_token');
    }

    private function project(string $token): string
    {
        return $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'QA project'])->assertCreated()->json('data.id');
    }

    private function createCaseFixture(string $token, string $project, ?string $section = null): array
    {
        return $this->withToken($token)->postJson('/api/v1/projects/'.$project.'/cases', ['section_id' => $section, 'title' => 'Valid sign in', 'priority' => 'high', 'content' => ['checklist' => [['text' => 'Enter valid credentials', 'expected' => 'Dashboard appears', 'required' => true], ['text' => 'Check greeting', 'expected' => 'Name appears', 'required' => false]]]])->assertCreated()->json('data');
    }

    public function test_project_isolation_and_independent_review(): void
    {
        $author = $this->account(true);
        $reviewer = $this->account(true);
        $outsider = $this->account();
        $a = $this->token($author);
        $b = $this->token($reviewer);
        $p = $this->project($a);
        $c = $this->createCaseFixture($a, $p);
        $url = '/api/v1/projects/'.$p.'/cases/'.$c['id'];
        $this->withToken($this->token($outsider))->getJson($url)->assertNotFound();
        $this->withToken($a)->postJson($url.'/review', ['decision' => 'submit'])->assertOk();
        $this->withToken($a)->postJson($url.'/review', ['decision' => 'approve'])->assertConflict();
        $this->withToken($b)->postJson($url.'/review', ['decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('test_cases', ['id' => $c['id'], 'status' => 'approved']);
    }

    public function test_snapshot_and_append_only_results(): void
    {
        $a = $this->token($this->account(true));
        $b = $this->token($this->account(true));
        $p = $this->project($a);
        $c = $this->createCaseFixture($a, $p);
        $base = '/api/v1/projects/'.$p;
        $this->withToken($a)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'submit'])->assertOk();
        $this->withToken($b)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'approve'])->assertOk();
        $run = $this->withToken($a)->postJson($base.'/runs', ['title' => 'Baseline', 'cases' => [['id' => $c['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $run)->first();
        $resultUrl = $base.'/runs/'.$run.'/items/'.$item->id.'/results';
        $this->withToken($a)->postJson($resultUrl, ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['fail', 'pass']])->assertUnprocessable();
        $v = ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass', 'pass']];
        $this->withToken($a)->postJson($resultUrl, $v)->assertCreated();
        $this->withToken($a)->postJson($resultUrl, $v)->assertCreated();
        $this->assertDatabaseCount('test_results', 1);
        $this->withToken($a)->postJson($resultUrl, array_replace($v, ['outcome' => 'fail', 'checks' => ['fail', 'pass']]))->assertConflict();
        $this->withToken($a)->putJson($base.'/cases/'.$c['id'], ['title' => 'Changed', 'priority' => 'low', 'content' => json_decode($c['content'], true)], ['If-Match' => '1'])->assertOk();
        $this->assertSame('Valid sign in', json_decode(DB::table('run_items')->find($item->id)->snapshot, true)['title']);
        $this->assertDatabaseCount('case_revisions', 2);
    }

    public function test_account_deactivation_revokes_existing_access(): void
    {
        $u = $this->account(true);
        $token = $this->token($u);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $u->active = false;
        $u->save();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_group_deny_overrides_administrator_role_and_direct_allow(): void
    {
        $admin = $this->account(true);
        $token = $this->token($admin);
        $p = $this->project($token);
        $member = $this->account();
        DB::table('project_members')->insert(['project_id' => $p, 'user_id' => $member->id, 'role' => 'admin', 'grants' => json_encode(['cases.create' => 'allow'])]);
        $g = (string) Str::uuid();
        DB::table('permission_groups')->insert(['id' => $g, 'project_id' => $p, 'name' => 'Restriction', 'active' => true, 'grants' => json_encode(['cases.create' => 'deny']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_group_members')->insert(['permission_group_id' => $g, 'user_id' => $member->id]);
        $this->withToken($this->token($member))->postJson('/api/v1/projects/'.$p.'/cases', ['title' => 'Blocked', 'priority' => 'low', 'content' => ['checklist' => [['text' => 'Step', 'expected' => 'Expected', 'required' => true]]]])->assertForbidden();
    }

    public function test_forced_closure_does_not_create_pass(): void
    {
        $a = $this->token($this->account(true));
        $b = $this->token($this->account(true));
        $p = $this->project($a);
        $c = $this->createCaseFixture($a, $p);
        $base = '/api/v1/projects/'.$p;
        $this->withToken($a)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'submit']);
        $this->withToken($b)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'approve']);
        $run = $this->withToken($a)->postJson($base.'/runs', ['title' => 'Baseline', 'cases' => [['id' => $c['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $run)->first();
        $this->withToken($a)->postJson($base.'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'fail', 'checks' => ['fail', 'pass']])->assertCreated();
        $d = $this->withToken($a)->postJson($base.'/defects', ['title' => 'Failure', 'description' => 'Dashboard does not appear', 'severity' => 'high', 'run_item_id' => $item->id])->assertCreated()->json('data.id');
        $this->withToken($a)->postJson($base.'/defects/'.$d.'/transition', ['status' => 'closed', 'force' => true, 'confirmed' => true, 'reason' => 'Business accepts this known issue.'])->assertOk();
        $this->assertDatabaseHas('defects', ['id' => $d, 'closure_type' => 'forced', 'verified_at' => null]);
        $this->assertDatabaseMissing('test_results', ['outcome' => 'pass']);
    }

    public function test_password_challenge_is_separate_and_has_no_refresh_cookie(): void
    {
        $u = $this->account();
        $u->must_change_password = true;
        $u->save();
        $response = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'PasswordForTesting123!'])->assertOk();
        $jwt = $response->json('data.access_token');
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
        $this->assertSame('afra-password-change', $payload['aud']);
        $this->assertSame(300, $payload['exp'] - $payload['iat']);
        $this->withToken($jwt)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($jwt)->postJson('/api/v1/me/password', ['current_password' => 'PasswordForTesting123!', 'password' => 'DifferentPassword123!', 'password_confirmation' => 'DifferentPassword123!'])->assertOk();
        $this->withToken($jwt)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_refresh_replay_commits_family_revocation(): void
    {
        $u = $this->account();
        $login = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'PasswordForTesting123!'])->assertOk();
        $session = DB::table('auth_sessions')->where('user_id', $u->id)->first();
        $spent = bin2hex(random_bytes(32));
        DB::table('spent_refresh_tokens')->insert(['hash' => hash('sha256', $spent), 'session_id' => $session->id, 'created_at' => now()]);
        $this->withCredentials()->withUnencryptedCookie('afra_refresh', $spent)->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/refresh')->assertUnauthorized();
        $this->assertNotNull(DB::table('auth_sessions')->find($session->id)->revoked_at);
        $this->withToken($login->json('data.access_token'))->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expiry_blocks_access_and_login(): void
    {
        $u = $this->account();
        $token = $this->token($u);
        $u->expires_at = now()->subSecond();
        $u->save();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'PasswordForTesting123!'])->assertUnauthorized();
    }

    public function test_normal_closure_requires_latest_linked_pass_after_resolution(): void
    {
        $a = $this->token($this->account(true));
        $b = $this->token($this->account(true));
        $p = $this->project($a);
        $c = $this->createCaseFixture($a, $p);
        $base = '/api/v1/projects/'.$p;
        $this->withToken($a)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'submit'])->assertOk();
        $this->withToken($b)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'approve'])->assertOk();
        $run = $this->withToken($a)->postJson($base.'/runs', ['title' => 'Original', 'cases' => [['id' => $c['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $run)->first();
        $this->withToken($a)->postJson($base.'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'fail', 'checks' => ['fail', 'pass']])->assertCreated();
        $d = $this->withToken($a)->postJson($base.'/defects', ['run_item_id' => $item->id, 'title' => 'Defect', 'description' => 'Actual outcome differs', 'severity' => 'high'])->assertCreated()->json('data.id');
        $this->withToken($a)->postJson($base.'/defects/'.$d.'/transition', ['status' => 'resolved', 'reason' => 'The correction is now ready.'])->assertOk();
        $this->withToken($a)->postJson($base.'/defects/'.$d.'/transition', ['status' => 'closed', 'reason' => 'Verification requested.'])->assertConflict();
        $retest = $this->withToken($a)->postJson($base.'/defects/'.$d.'/retest')->assertCreated()->json('data');
        $url = $base.'/runs/'.$retest['run_id'].'/items/'.$retest['item_id'].'/results';
        $this->travel(2)->seconds();
        $this->withToken($a)->postJson($url, ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass', 'pass']])->assertCreated();
        $this->travel(2)->seconds();
        $this->withToken($a)->postJson($url, ['request_id' => (string) Str::uuid(), 'outcome' => 'fail', 'checks' => ['fail', 'pass']])->assertCreated();
        $this->withToken($a)->postJson($base.'/defects/'.$d.'/transition', ['status' => 'closed', 'reason' => 'An older Pass must not hide a newer Fail.'])->assertConflict();
        $this->travel(2)->seconds();
        $this->withToken($a)->postJson($url, ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass', 'pass']])->assertCreated();
        $this->withToken($a)->postJson($base.'/defects/'.$d.'/transition', ['status' => 'closed', 'reason' => 'Latest linked retest is successful.'])->assertOk();
        $this->assertDatabaseHas('defects', ['id' => $d, 'closure_type' => 'verified']);
        $this->assertNotNull(DB::table('defects')->find($d)->verified_at);
    }

    public function test_refresh_rejects_missing_csrf_and_rotates_valid_cookie(): void
    {
        $u = $this->account();
        $login = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'PasswordForTesting123!'])->assertOk();
        $cookie = $login->getCookie('afra_refresh', false)->getValue();
        $csrf = $login->json('data.csrf_token');
        $this->withCredentials()->withUnencryptedCookie('afra_refresh', $cookie)->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/refresh')->assertUnauthorized();
        $new = $this->withHeader('X-CSRF-Token', $csrf)->postJson('/api/auth/refresh')->assertOk();
        $this->assertNotSame($cookie, $new->getCookie('afra_refresh', false)->getValue());
        $this->assertDatabaseCount('spent_refresh_tokens', 1);
        $this->withToken($new->json('data.access_token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_twenty_mib_evidence_is_encrypted_chunked_and_project_private(): void
    {
        $a = $this->token($this->account(true));
        $outsider = $this->token($this->account());
        $project = $this->project($a);
        $content = str_repeat('A', 20 * 1024 * 1024);
        $file = UploadedFile::fake()->createWithContent('training-proof.txt', $content);
        $response = $this->withToken($a)->post('/api/v1/projects/'.$project.'/files', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('attachments', ['id' => $id, 'size' => strlen($content), 'sha256' => hash('sha256', $content)]);
        $this->assertSame(80, DB::table('attachment_chunks')->where('attachment_id', $id)->count());
        $first = DB::table('attachment_chunks')->where('attachment_id', $id)->orderBy('position')->value('ciphertext');
        $this->assertNotSame(substr($content, 0, 262144), $first);
        $this->assertSame(substr($content, 0, 262144), Crypt::decryptString($first));
        $this->withToken($outsider)->get('/api/v1/projects/'.$project.'/files/'.$id, ['Accept' => 'application/json'])->assertNotFound();
        $download = $this->withToken($a)->get('/api/v1/projects/'.$project.'/files/'.$id)->assertOk();
        $this->assertSame(hash('sha256', $content), hash('sha256', $download->streamedContent()));
        $tooLarge = UploadedFile::fake()->create('too-large.txt', 20481, 'text/plain');
        $this->withToken($a)->post('/api/v1/projects/'.$project.'/files', ['file' => $tooLarge], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_hidden_sections_are_filtered_from_lists_history_counts_and_evidence(): void
    {
        $a = $this->token($this->account(true));
        $b = $this->token($this->account(true));
        $p = $this->project($a);
        $base = '/api/v1/projects/'.$p;
        $section = $this->withToken($a)->postJson($base.'/sections', ['name' => 'Private'])->assertCreated()->json('data.id');
        $child = $this->withToken($a)->postJson($base.'/sections', ['name' => 'Visible exception', 'parent_id' => $section])->assertCreated()->json('data.id');
        $visible = $this->createCaseFixture($a, $p);
        $hidden = $this->createCaseFixture($a, $p, $section);
        foreach ([$visible, $hidden] as $c) {
            $this->withToken($a)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'submit'])->assertOk();
            $this->withToken($b)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'approve'])->assertOk();
        }
        $run = $this->withToken($a)->postJson($base.'/runs', ['title' => 'Mixed run', 'cases' => [['id' => $visible['id'], 'version' => 1], ['id' => $hidden['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $run)->where('test_case_id', $hidden['id'])->first();
        $file = $this->withToken($a)->post($base.'/files', ['entity_type' => 'case', 'entity_id' => $hidden['id'], 'file' => UploadedFile::fake()->createWithContent('proof.txt', 'Synthetic private evidence')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->withToken($a)->postJson($base.'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'fail', 'checks' => ['fail', 'pass']])->assertCreated();
        $defect = $this->withToken($a)->postJson($base.'/defects', ['run_item_id' => $item->id, 'title' => 'Private failure', 'description' => 'Private expected result failed', 'severity' => 'critical'])->assertCreated()->json('data.id');
        $member = $this->account();
        DB::table('project_members')->insert(['project_id' => $p, 'user_id' => $member->id, 'role' => 'admin']);
        DB::table('section_rules')->insert([['section_id' => $section, 'user_id' => $member->id, 'action' => 'view', 'effect' => 'deny'], ['section_id' => $child, 'user_id' => $member->id, 'action' => 'view', 'effect' => 'allow']]);
        $m = $this->token($member);
        $this->withToken($m)->getJson($base.'/cases')->assertOk()->assertJsonPath('data.total', 1);
        $this->withToken($m)->getJson($base.'/cases/'.$hidden['id'])->assertNotFound();
        $this->withToken($m)->getJson($base.'/sections')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $child)->assertJsonPath('data.0.parent_id', null);
        $this->withToken($m)->getJson($base.'/runs/'.$run)->assertOk()->assertJsonCount(1, 'data.items');
        $this->withToken($m)->getJson($base.'/defects')->assertOk()->assertJsonPath('data.total', 0);
        $this->withToken($m)->getJson($base.'/dashboard')->assertOk()->assertJsonPath('data.test_cases', 1)->assertJsonPath('data.open_critical', 0);
        $this->withToken($m)->getJson($base.'/files/'.$file)->assertNotFound();
        $this->withToken($m)->getJson($base.'/files?entity_type=case&entity_id='.$hidden['id'])->assertNotFound();
        $audit = $this->withToken($m)->getJson($base.'/audit')->assertOk()->json('data.data');
        $this->assertFalse(collect($audit)->contains(fn ($event) => in_array($event['entity_id'], [$hidden['id'], $defect, $section], true)));
        $this->withToken($m)->postJson($base.'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass', 'pass']])->assertNotFound();
        $this->withToken($m)->postJson($base.'/defects/'.$defect.'/transition', ['status' => 'closed', 'force' => true, 'confirmed' => true, 'reason' => 'A hidden defect cannot be changed.'])->assertNotFound();
    }

    public function test_recorded_result_evidence_cannot_be_rebound_or_changed_by_replay(): void
    {
        $a = $this->token($this->account(true));
        $b = $this->token($this->account(true));
        $p = $this->project($a);
        $base = '/api/v1/projects/'.$p;
        $c = $this->createCaseFixture($a, $p);
        $this->withToken($a)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'submit'])->assertOk();
        $this->withToken($b)->postJson($base.'/cases/'.$c['id'].'/review', ['decision' => 'approve'])->assertOk();
        $run = $this->withToken($a)->postJson($base.'/runs', ['title' => 'Evidence run', 'cases' => [['id' => $c['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $run)->first();
        $file = $this->withToken($a)->post($base.'/files', ['entity_type' => 'run_item', 'entity_id' => $item->id, 'file' => UploadedFile::fake()->createWithContent('proof.txt', 'Synthetic immutable proof')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $url = $base.'/runs/'.$run.'/items/'.$item->id.'/results';
        $v = ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass', 'pass'], 'evidence_ids' => [$file]];
        $id = $this->withToken($a)->postJson($url, $v)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('attachments', ['id' => $file, 'entity_type' => 'result', 'entity_id' => $id]);
        $this->withToken($a)->postJson($url, $v)->assertCreated();
        $this->withToken($a)->postJson($url, array_replace($v, ['evidence_ids' => []]))->assertConflict();
        $this->withToken($a)->postJson($url, array_replace($v, ['request_id' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('test_results', 1);
        $this->withToken($a)->post($base.'/files', ['entity_type' => 'result', 'entity_id' => $id, 'file' => UploadedFile::fake()->createWithContent('replacement.txt', 'Not allowed')], ['Accept' => 'application/json'])->assertConflict();
    }
}
