<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequirementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $author;

    private string $reviewer;

    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-req-private.pem', $private);
        file_put_contents('/tmp/afra-req-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-req-private.pem', 'afra.public_key' => '/tmp/afra-req-public.pem']);
        $this->author = $this->login(true);
        $this->reviewer = $this->login(true);
        $this->project = $this->withToken($this->author)->postJson('/api/v1/projects', ['name' => 'Requirements training'])->assertCreated()->json('data.id');
    }

    private function login(bool $admin): string
    {
        $user = User::factory()->create(['password' => 'RequirementTesting123!']);
        $user->forceFill(['is_system_admin' => $admin, 'must_change_password' => false])->save();

        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'RequirementTesting123!'])->assertOk()->json('data.access_token');
    }

    private function base(): string
    {
        return '/api/v1/projects/'.$this->project;
    }

    private function body(string $description = 'An active account can sign in.'): array
    {
        return ['title' => 'Sign in requirement', 'content' => ['description' => $description, 'acceptance_criteria' => 'Valid credentials reach the dashboard.', 'priority' => 'high', 'product_version' => 'Release A'], 'reason' => 'Record the agreed acceptance criteria.'];
    }

    private function requirement(): array
    {
        return $this->withToken($this->author)->postJson($this->base().'/requirements', $this->body())->assertCreated()->json('data');
    }

    private function linkedCase(array $requirement): array
    {
        return $this->withToken($this->author)->postJson($this->base().'/cases', ['title' => 'Sign in test', 'priority' => 'high', 'content' => ['checklist' => [['text' => 'Sign in', 'expected' => 'Dashboard', 'required' => true]], 'requirements' => [['id' => $requirement['id'], 'version' => $requirement['version']]]]])->assertCreated()->json('data');
    }

    private function approve(array $case): void
    {
        $url = $this->base().'/cases/'.$case['id'].'/review';
        $this->withToken($this->author)->postJson($url, ['decision' => 'submit', 'version' => $case['version']])->assertOk();
        $this->withToken($this->reviewer)->postJson($url, ['decision' => 'approve', 'version' => $case['version']])->assertOk();
    }

    private function createExecution(array $case): string
    {
        return $this->withToken($this->author)->postJson($this->base().'/runs', ['title' => 'Requirement execution', 'cases' => [['id' => $case['id'], 'version' => $case['version']]]])->assertCreated()->json('data.id');
    }

    private function pass(string $run): void
    {
        $item = DB::table('run_items')->where('test_run_id', $run)->first();
        $this->withToken($this->author)->postJson($this->base().'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'checks' => ['pass'], 'outcome' => 'pass'])->assertCreated();
    }

    public function test_revision_history_and_generic_route_cannot_bypass_validation(): void
    {
        $r = $this->requirement();
        $url = $this->base().'/requirements/'.$r['id'];
        $this->withToken($this->author)->putJson($url, $this->body('New description'), ['If-Match' => '1'])->assertOk()->assertJsonPath('data.version', 2);
        $this->withToken($this->author)->putJson($url, $this->body(), ['If-Match' => '1'])->assertConflict();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonCount(2, 'data.revisions');
        $old = json_decode(DB::table('requirement_revisions')->where('requirement_id', $r['id'])->where('version', 1)->value('content'), true);
        $this->assertSame($this->body()['content'], $old);
        $this->withToken($this->author)->postJson($this->base().'/records/requirements', ['title' => 'Bypass', 'content' => []])->assertUnprocessable();
    }

    public function test_compatibility_does_not_reuse_an_old_pass_for_a_new_requirement(): void
    {
        $r = $this->requirement();
        $case = $this->linkedCase($r);
        $this->approve($case);
        $oldRun = $this->createExecution($case);
        $this->pass($oldRun);
        $url = $this->base().'/requirements/'.$r['id'];
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.cases.data.0.coverage', 'pass');
        $this->withToken($this->author)->putJson($url, $this->body('Updated acceptance scope'), ['If-Match' => '1'])->assertOk();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.cases.data.0.coverage', 'stale');
        $this->withToken($this->author)->postJson($this->base().'/runs', ['title' => 'Stale', 'cases' => [['id' => $case['id'], 'version' => 1]]])->assertConflict();
        $confirmation = ['case_id' => $case['id'], 'case_version' => 1, 'requirement_version' => 2, 'reason' => 'Existing steps still fully cover the new acceptance criteria.'];
        $this->withToken($this->author)->postJson($url.'/compatibility', $confirmation)->assertConflict();
        $this->withToken($this->reviewer)->postJson($url.'/compatibility', $confirmation)->assertOk();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.cases.data.0.coverage', 'not_planned');
        $newRun = $this->createExecution($case);
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.cases.data.0.coverage', 'not_run');
        $this->pass($newRun);
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.cases.data.0.coverage', 'pass');
        $old = json_decode(DB::table('run_items')->where('test_run_id', $oldRun)->value('requirement_bindings'), true);
        $this->assertSame(1, $old[0]['version']);
        $this->assertDatabaseCount('test_results', 2);
    }

    public function test_requirement_change_while_review_is_pending_invalidates_approval(): void
    {
        $r = $this->requirement();
        $case = $this->linkedCase($r);
        $review = $this->base().'/cases/'.$case['id'].'/review';
        $this->withToken($this->author)->postJson($review, ['decision' => 'submit', 'version' => 1])->assertOk();
        $url = $this->base().'/requirements/'.$r['id'];
        $this->withToken($this->author)->putJson($url, $this->body('Updated scope'), ['If-Match' => '1'])->assertOk();
        $this->withToken($this->reviewer)->postJson($url.'/compatibility', ['case_id' => $case['id'], 'case_version' => 1, 'requirement_version' => 2, 'reason' => 'The case remains compatible after independent inspection.'])->assertOk();
        $this->withToken($this->reviewer)->postJson($review, ['decision' => 'approve', 'version' => 1])->assertConflict();
        $this->withToken($this->reviewer)->postJson($review, ['decision' => 'reject', 'version' => 1, 'reason' => 'Refresh requirement scope before the next review.'])->assertOk();
        $this->approve($case);
    }

    public function test_cross_project_links_and_outsiders_are_rejected(): void
    {
        $r = $this->requirement();
        $other = $this->withToken($this->author)->postJson('/api/v1/projects', ['name' => 'Other project'])->assertCreated()->json('data.id');
        $this->withToken($this->author)->postJson('/api/v1/projects/'.$other.'/cases', ['title' => 'Cross-project', 'priority' => 'high', 'content' => ['checklist' => [['text' => 'Act', 'expected' => 'Observe', 'required' => true]], 'requirements' => [['id' => $r['id'], 'version' => 1]]]])->assertNotFound();
        $this->assertDatabaseCount('test_cases', 0);
        $outsider = $this->login(false);
        $this->withToken($outsider)->getJson($this->base().'/requirements/'.$r['id'])->assertNotFound();
        $this->withToken($outsider)->getJson($this->base())->assertNotFound();
        $this->withToken($this->author)->getJson($this->base())->assertOk()->assertJsonPath('data.id', $this->project);
        $this->withToken($this->author)->getJson('/api/v1/projects?q=Other&per_page=100')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_archive_preserves_history_and_blocks_new_execution(): void
    {
        $r = $this->requirement();
        $case = $this->linkedCase($r);
        $this->approve($case);
        $run = $this->createExecution($case);
        $url = $this->base().'/requirements/'.$r['id'];
        $this->withToken($this->author)->postJson($url.'/archive', ['reason' => 'This requirement is retained as historical scope.'], ['If-Match' => '1'])->assertOk();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonCount(1, 'data.revisions');
        $this->withToken($this->author)->postJson($this->base().'/runs', ['title' => 'Archived scope', 'cases' => [['id' => $case['id'], 'version' => 1]]])->assertConflict();
        $this->assertDatabaseHas('test_runs', ['id' => $run]);
    }

    public function test_hidden_section_cases_do_not_leak_through_requirement_impact(): void
    {
        $r = $this->requirement();
        $case = $this->linkedCase($r);
        $section = $this->withToken($this->author)->postJson($this->base().'/sections', ['name' => 'Private section'])->assertCreated()->json('data.id');
        DB::table('test_cases')->where('id', $case['id'])->update(['section_id' => $section]);
        $viewer = User::factory()->create(['password' => 'RequirementTesting123!']);
        $viewer->forceFill(['must_change_password' => false])->save();
        DB::table('project_members')->insert(['project_id' => $this->project, 'user_id' => $viewer->id, 'role' => 'viewer']);
        DB::table('section_rules')->insert(['section_id' => $section, 'user_id' => $viewer->id, 'action' => 'view', 'effect' => 'deny']);
        $token = $this->postJson('/api/auth/login', ['email' => $viewer->email, 'password' => 'RequirementTesting123!'])->assertOk()->json('data.access_token');
        $this->withToken($token)->getJson($this->base().'/requirements/'.$r['id'])->assertOk()->assertJsonPath('data.cases.total', 0)->assertJsonCount(0, 'data.compatibilities');
    }
}
