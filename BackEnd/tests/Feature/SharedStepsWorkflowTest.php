<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedStepsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $author;

    private string $reviewer;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-shared-private.pem', $private);
        file_put_contents('/tmp/afra-shared-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-shared-private.pem', 'afra.public_key' => '/tmp/afra-shared-public.pem']);
        $this->author = $this->login();
        $this->reviewer = $this->login();
        $project = $this->withToken($this->author)->postJson('/api/v1/projects', ['name' => 'Shared workflow'])->assertCreated()->json('data.id');
        $this->base = '/api/v1/projects/'.$project;
    }

    private function login(): string
    {
        $user = User::factory()->create(['password' => 'SharedTesting123!']);
        $user->forceFill(['is_system_admin' => true, 'must_change_password' => false])->save();

        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'SharedTesting123!'])->assertOk()->json('data.access_token');
    }

    private function step(string $text): array
    {
        return ['text' => $text, 'expected' => 'Expected '.$text, 'required' => true];
    }

    private function block(): string
    {
        return $this->withToken($this->author)->postJson($this->base.'/records/shared-steps', ['title' => 'Login steps', 'content' => ['checklist' => [$this->step('Sign in')]]])->assertCreated()->json('data.id');
    }

    private function approve(string $id, int $version): void
    {
        $url = $this->base.'/shared-steps/'.$id.'/review';
        $this->withToken($this->author)->postJson($url, ['version' => $version, 'decision' => 'submit'])->assertOk();
        $this->withToken($this->author)->postJson($url, ['version' => $version, 'decision' => 'approve'])->assertForbidden();
        $this->withToken($this->reviewer)->postJson($url, ['version' => $version, 'decision' => 'approve'])->assertOk();
    }

    public function test_pinned_reviewed_steps_remain_frozen_until_explicit_case_update_or_detach(): void
    {
        $id = $this->block();
        $body = ['title' => 'Checkout', 'priority' => 'high', 'content' => ['checklist' => [$this->step('Pay')], 'shared_blocks' => [['id' => $id, 'version' => 1, 'position' => 0]]]];
        $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertConflict();
        $this->approve($id, 1);
        $case = $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertCreated()->json('data.id');
        $content = json_decode(DB::table('test_cases')->where('id', $case)->value('content'), true);
        $this->assertSame(['Sign in', 'Pay'], array_column($content['checklist'], 'text'));
        $this->assertSame(['Pay'], array_column($content['local_checklist'], 'text'));
        $this->withToken($this->author)->putJson($this->base.'/records/shared-steps/'.$id, ['title' => 'Login v2', 'content' => ['checklist' => [$this->step('New sign in')]]], ['If-Match' => '1'])->assertOk();
        $this->withToken($this->author)->postJson($this->base.'/shared-steps/'.$id.'/review', ['version' => 1, 'decision' => 'approve'])->assertConflict();
        $this->approve($id, 2);
        $this->assertSame('Sign in', json_decode(DB::table('test_cases')->where('id', $case)->value('content'), true)['checklist'][0]['text']);
        $body['content']['shared_blocks'][0]['version'] = 2;
        $this->withToken($this->author)->putJson($this->base.'/cases/'.$case, $body, ['If-Match' => '1'])->assertOk();
        $this->assertSame('draft', DB::table('test_cases')->where('id', $case)->value('status'));
        $old = json_decode(DB::table('case_revisions')->where('test_case_id', $case)->where('version', 1)->value('snapshot'), true);
        $this->assertSame('Sign in', $old['content']['checklist'][0]['text']);
        $body['content'] = ['checklist' => [$this->step('New sign in'), $this->step('Pay')]];
        $this->withToken($this->author)->putJson($this->base.'/cases/'.$case, $body, ['If-Match' => '2'])->assertOk();
        $this->assertArrayNotHasKey('shared_sources', json_decode(DB::table('test_cases')->where('id', $case)->value('content'), true));
        $this->withToken($this->author)->getJson($this->base.'/shared-steps/'.$id.'/consumers')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_malformed_steps_positions_cross_project_and_forged_sources_are_rejected(): void
    {
        $this->withToken($this->author)->postJson($this->base.'/records/shared-steps', ['title' => 'Malformed', 'content' => ['checklist' => [['text' => 'Missing expected']]]])->assertUnprocessable();
        $id = $this->block();
        $this->approve($id, 1);
        $body = ['title' => 'Bad', 'priority' => 'low', 'content' => ['checklist' => [$this->step('Local')], 'shared_blocks' => [['id' => $id, 'version' => 1, 'position' => 2]]]];
        $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertUnprocessable();
        $body['content']['shared_blocks'][0]['position'] = 0;
        $body['content']['shared_sources'] = [['checklist' => [$this->step('Forged')]]];
        $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertUnprocessable();
        unset($body['content']['shared_sources']);
        $other = $this->withToken($this->author)->postJson('/api/v1/projects', ['name' => 'Other'])->assertCreated()->json('data.id');
        $this->withToken($this->author)->postJson('/api/v1/projects/'.$other.'/cases', $body)->assertNotFound();
    }

    public function test_shared_listing_is_lightweight_history_is_paginated_and_archived_pins_remain_editable(): void
    {
        $id = $this->block();
        $this->approve($id, 1);
        $body = ['title' => 'Pinned archived block', 'priority' => 'low', 'content' => ['checklist' => [], 'shared_blocks' => [['id' => $id, 'version' => 1, 'position' => 0]]]];
        $case = $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertCreated()->json('data.id');
        $list = $this->withToken($this->author)->getJson($this->base.'/records/shared-steps?summary=1')->assertOk()->assertJsonPath('data.data.0.approved_version', 1)->assertJsonPath('data.data.0.review_status', 'approved')->json('data.data.0');
        $this->assertArrayNotHasKey('content', $list);
        for ($version = 2; $version <= 28; $version++) {
            DB::table('project_record_revisions')->insert(['record_id' => $id, 'version' => $version, 'snapshot' => json_encode(['title' => 'Draft '.$version, 'content' => ['checklist' => [$this->step('Draft')]]]), 'created_at' => now()]);
        }
        DB::table('project_records')->where('id', $id)->update(['version' => 28]);
        $this->withToken($this->author)->getJson($this->base.'/records/shared-steps/'.$id)->assertOk()->assertJsonCount(25, 'data.revisions')->assertJsonPath('data.approved_version', 1)->assertJsonPath('data.revision_total', 28);
        $this->withToken($this->author)->getJson($this->base.'/records/shared-steps/'.$id.'?revision_page=2')->assertOk()->assertJsonCount(3, 'data.revisions')->assertJsonPath('data.revisions.2.version', 1);
        $this->withToken($this->author)->postJson($this->base.'/records/shared-steps/'.$id.'/archive', ['reason' => 'Archived for controlled retirement'], ['If-Match' => '28'])->assertOk();
        $this->withToken($this->author)->postJson($this->base.'/cases', $body)->assertConflict();
        $body['title'] = 'Existing consumer edited';
        $this->withToken($this->author)->putJson($this->base.'/cases/'.$case, $body, ['If-Match' => '1'])->assertOk();
        $content = json_decode(DB::table('test_cases')->where('id', $case)->value('content'), true);
        $this->assertSame('Sign in', $content['checklist'][0]['text']);
    }
}
