<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningWorkflowTest extends TestCase
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
        file_put_contents('/tmp/afra-plan-private.pem', $private);
        file_put_contents('/tmp/afra-plan-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-plan-private.pem', 'afra.public_key' => '/tmp/afra-plan-public.pem']);
        $this->author = $this->login(true);
        $this->reviewer = $this->login(true);
        $this->project = $this->withToken($this->author)->postJson('/api/v1/projects', ['name' => 'Planning'])->assertCreated()->json('data.id');
    }

    private function login(bool $admin): string
    {
        $user = User::factory()->create(['password' => 'PlanningTesting123!']);
        $user->forceFill(['is_system_admin' => $admin, 'must_change_password' => false])->save();

        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'PlanningTesting123!'])->assertOk()->json('data.access_token');
    }

    private function base(): string
    {
        return '/api/v1/projects/'.$this->project;
    }

    private function record(string $kind, array $content): array
    {
        return $this->withToken($this->author)->postJson($this->base().'/records/'.$kind, ['title' => 'Training '.$kind, 'content' => $content])->assertCreated()->json('data');
    }

    private function caseFixture(string $title = 'Case {{name}}'): array
    {
        $case = $this->withToken($this->author)->postJson($this->base().'/cases', ['title' => $title, 'priority' => 'high', 'content' => ['variables' => ['name' => 'Default', 'enabled' => true, 'count' => 77], 'checklist' => [['text' => 'Act {{name}}', 'expected' => 'enabled {{enabled}}; count {{count}}', 'required' => true]]]])->assertCreated()->json('data');
        $url = $this->base().'/cases/'.$case['id'].'/review';
        $this->withToken($this->author)->postJson($url, ['decision' => 'submit', 'version' => 1])->assertOk();
        $this->withToken($this->reviewer)->postJson($url, ['decision' => 'approve', 'version' => 1])->assertOk();

        return $case;
    }

    private function fixtures(): array
    {
        $a = $this->caseFixture();
        $b = $this->caseFixture('Second {{name}}');
        $content = ['columns' => [['key' => 'name', 'type' => 'string', 'required' => true], ['key' => 'enabled', 'type' => 'boolean', 'required' => true], ['key' => 'count', 'type' => 'number', 'required' => true]], 'rows' => [['name' => 'A', 'enabled' => false, 'count' => 0], ['name' => 'B', 'enabled' => true, 'count' => 2]]];
        $data = $this->record('datasets', $content);
        $profile = $this->record('configuration-profiles', ['rows' => [['id' => (string) Str::uuid(), 'name' => 'Desktop', 'required' => true, 'dataset_id' => $data['id']], ['id' => (string) Str::uuid(), 'name' => 'Mobile', 'required' => false, 'dataset_id' => $data['id']]]]);
        $plan = $this->record('plans', ['case_ids' => [$a['id'], $b['id']], 'configuration_profile_id' => $profile['id']]);

        return [$plan, $data, $content];
    }

    private function preview(string $id): array
    {
        return $this->withToken($this->author)->postJson($this->base().'/runs/preview', ['plan_id' => $id])->assertOk()->json('data');
    }

    public function test_two_cases_two_configurations_two_rows_freeze_eight_items_and_replay_is_idempotent(): void
    {
        [$plan, $dataset, $content] = $this->fixtures();
        $preview = $this->preview($plan['id']);
        $this->assertSame(2, $preview['unique_cases']);
        $this->assertSame(8, $preview['total_items']);
        $this->assertSame(4, $preview['required_items']);
        $this->assertSame('enabled false; count 0', $preview['items'][0]['snapshot']['content']['checklist'][0]['expected']);
        $body = ['title' => 'Frozen matrix execution', 'preview_token' => $preview['preview_token']];
        $run = $this->withToken($this->author)->postJson($this->base().'/planned-runs', $body)->assertCreated()->json('data.id');
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', $body)->assertCreated()->assertJsonPath('data.id', $run);
        $this->assertDatabaseCount('test_runs', 1);
        $this->assertDatabaseCount('run_items', 8);
        $content['rows'][0]['name'] = 'Changed';
        $this->withToken($this->author)->putJson($this->base().'/records/datasets/'.$dataset['id'], ['title' => 'Changed dataset', 'content' => $content], ['If-Match' => '1'])->assertOk();
        $item = DB::table('run_items')->where('test_run_id', $run)->orderBy('id')->get()->first(fn ($row) => json_decode($row->data_snapshot, true)['row'] === 0);
        $this->assertSame('A', json_decode($item->data_snapshot, true)['values']['name']);
        $this->assertFalse(json_decode($item->data_snapshot, true)['values']['enabled']);
        $this->assertSame(0, json_decode($item->data_snapshot, true)['values']['count']);
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Different title', 'preview_token' => $preview['preview_token']])->assertConflict();
    }

    public function test_required_variants_cannot_be_removed_but_optional_variants_can(): void
    {
        [$plan] = $this->fixtures();
        $preview = $this->preview($plan['id']);
        $required = array_column(array_values(array_filter($preview['items'], fn ($item) => $item['required'])), 'key');
        $body = ['title' => 'Required-only execution', 'preview_token' => $preview['preview_token'], 'selected' => array_slice($required, 1)];
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', $body)->assertUnprocessable();
        $this->assertDatabaseCount('test_runs', 0);
        $body['selected'] = $required;
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', $body)->assertCreated();
        $this->assertDatabaseCount('run_items', 4);
    }

    public function test_changed_dataset_or_expired_preview_cannot_be_confirmed(): void
    {
        [$plan, $data, $content] = $this->fixtures();
        $preview = $this->preview($plan['id']);
        $content['rows'][0]['name'] = 'Changed';
        $this->withToken($this->author)->putJson($this->base().'/records/datasets/'.$data['id'], ['title' => 'Changed', 'content' => $content], ['If-Match' => '1'])->assertOk();
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Stale', 'preview_token' => $preview['preview_token']])->assertConflict();
        $fresh = $this->preview($plan['id']);
        $this->travel(6)->minutes();
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Expired', 'preview_token' => $fresh['preview_token']])->assertStatus(410);
        $this->assertDatabaseCount('test_runs', 0);
    }

    public function test_unresolved_variables_block_both_planned_and_direct_execution(): void
    {
        $case = $this->caseFixture('Missing {{undefined_value}}');
        $plan = $this->record('plans', ['case_ids' => [$case['id']]]);
        $this->withToken($this->author)->postJson($this->base().'/runs/preview', ['plan_id' => $plan['id']])->assertUnprocessable();
        $this->withToken($this->author)->postJson($this->base().'/runs', ['title' => 'Unresolved', 'cases' => [['id' => $case['id'], 'version' => 1]]])->assertUnprocessable();
        $this->assertDatabaseCount('test_runs', 0);
    }

    public function test_dataset_types_and_view_fields_are_allowlisted(): void
    {
        $this->withToken($this->author)->postJson($this->base().'/records/datasets', ['title' => 'Wrong type', 'content' => ['columns' => [['key' => 'enabled', 'type' => 'boolean', 'required' => true]], 'rows' => [['enabled' => 'false']]]])->assertUnprocessable();
        $this->withToken($this->author)->postJson($this->base().'/records/saved-views', ['title' => 'Injection', 'content' => ['visibility' => 'team', 'conditions' => ['field' => 'raw_sql', 'operator' => 'eq', 'value' => '1=1']]])->assertUnprocessable();
        $this->withToken($this->author)->postJson($this->base().'/records/saved-views', ['title' => 'Malformed values', 'content' => ['visibility' => 'team', 'conditions' => ['field' => 'priority', 'operator' => 'in', 'value' => [['nested']]]]])->assertUnprocessable();
    }

    public function test_personal_views_are_owner_only_and_team_views_re_evaluate_dynamic_scope(): void
    {
        $case = $this->caseFixture();
        $view = $this->record('saved-views', ['visibility' => 'personal', 'conditions' => ['mode' => 'and', 'rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'approved']]]]);
        $this->withToken($this->reviewer)->getJson($this->base().'/records/saved-views/'.$view['id'])->assertNotFound();
        $this->withToken($this->reviewer)->getJson($this->base().'/records/saved-views')->assertOk()->assertJsonPath('data.total', 0);
        $plan = $this->record('plans', ['case_ids' => [], 'saved_view_id' => $view['id']]);
        $preview = $this->preview($plan['id']);
        $this->assertSame(1, $preview['unique_cases']);
        $this->caseFixture('Additional {{name}}');
        $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Old dynamic scope', 'preview_token' => $preview['preview_token']])->assertConflict();
        $this->assertSame(2, $this->preview($plan['id'])['unique_cases']);
    }

    private function releaseRun(int $minimumPassRate = 100): array
    {
        [$plan] = $this->fixtures();
        $release = $this->record('releases', ['product_version' => '1.0', 'gates' => ['max_open_critical' => 0, 'minimum_pass_rate' => $minimumPassRate, 'allow_blocked' => false]]);
        $content = json_decode($plan['content'], true);
        $content['release_id'] = $release['id'];
        $this->withToken($this->author)->putJson($this->base().'/records/plans/'.$plan['id'], ['title' => $plan['title'], 'content' => $content], ['If-Match' => '1'])->assertOk();
        $preview = $this->preview($plan['id']);
        $run = $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Release baseline', 'preview_token' => $preview['preview_token']])->assertCreated()->json('data.id');

        return [$release, $run, $plan];
    }

    private function observe(string $run, string $item, string $outcome): void
    {
        $this->withToken($this->author)->postJson($this->base().'/runs/'.$run.'/items/'.$item.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => $outcome, 'checks' => [$outcome]])->assertCreated();
    }

    public function test_release_gate_counts_required_variants_and_latest_linked_retests(): void
    {
        [$release, $run] = $this->releaseRun();
        $url = $this->base().'/reports/releases/'.$release['id'];
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.ready', false)->assertJsonPath('data.summary.required_items', 4);
        $items = DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get();
        foreach ($items as $item) {
            $this->observe($run, $item->id, 'pass');
        }
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.ready', true)->assertJsonPath('data.summary.required_pass_rate', 100)->assertJsonPath('data.summary.optional_items', 4);
        $source = $items->first();
        $this->observe($run, $source->id, 'fail');
        $defect = $this->withToken($this->author)->postJson($this->base().'/defects', ['run_item_id' => $source->id, 'title' => 'Critical observation', 'description' => 'Actual result differs from the frozen expectation.', 'severity' => 'critical'])->assertCreated()->json('data.id');
        $this->withToken($this->author)->postJson($this->base().'/defects/'.$defect.'/transition', ['status' => 'resolved', 'reason' => 'A fix is ready for independent verification.'])->assertOk();
        $retest = $this->withToken($this->author)->postJson($this->base().'/defects/'.$defect.'/retest')->assertCreated()->json('data');
        $item = DB::table('run_items')->where('test_run_id', $retest['run_id'])->first();
        $this->assertSame($source->id, $item->baseline_item_id);
        $this->observe($retest['run_id'], $item->id, 'pass');
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.summary.required_pass_rate', 100)->assertJsonPath('data.ready', false);
        $this->withToken($this->author)->postJson($this->base().'/defects/'.$defect.'/transition', ['status' => 'closed', 'reason' => 'The linked retest verified the repair.'])->assertOk();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.ready', true);
        $this->withToken($this->author)->getJson($this->base().'/reports/runs/'.$run)->assertOk()->assertJsonPath('data.summary.required_pass_rate', 100);
    }

    public function test_hidden_failed_variants_never_produce_a_global_release_pass(): void
    {
        [$release, $run] = $this->releaseRun();
        $items = DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get();
        foreach ($items as $item) {
            $this->observe($run, $item->id, 'pass');
        }
        $hiddenCase = $items->last()->test_case_id;
        $private = $this->withToken($this->author)->postJson($this->base().'/sections', ['name' => 'Private section'])->assertCreated()->json('data.id');
        DB::table('run_items')->where('test_run_id', $run)->where('test_case_id', $hiddenCase)->update(['section_id' => $private]);
        $hidden = $items->last();
        $this->observe($run, $hidden->id, 'fail');
        $viewer = User::factory()->create(['password' => 'PlanningTesting123!']);
        $viewer->forceFill(['must_change_password' => false])->save();
        DB::table('project_members')->insert(['project_id' => $this->project, 'user_id' => $viewer->id, 'role' => 'viewer', 'grants' => json_encode(['reports.export' => 'deny'])]);
        DB::table('section_rules')->insert(['section_id' => $private, 'user_id' => $viewer->id, 'action' => 'view', 'effect' => 'deny']);
        $token = $this->postJson('/api/auth/login', ['email' => $viewer->email, 'password' => 'PlanningTesting123!'])->assertOk()->json('data.access_token');
        $this->withToken($token)->getJson($this->base().'/reports/releases/'.$release['id'])->assertOk()->assertJsonPath('data.summary.required_pass_rate', 100)->assertJsonPath('data.summary.total_items', 4)->assertJsonPath('data.checks.complete_scope', false)->assertJsonPath('data.ready', false);
        $this->withToken($token)->getJson($this->base().'/reports/runs/'.$run.'?export=1')->assertForbidden();
    }

    public function test_case_change_invalidates_a_successful_release_baseline(): void
    {
        [$release, $run] = $this->releaseRun();
        foreach (DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get() as $item) {
            $this->observe($run, $item->id, 'pass');
        }
        $url = $this->base().'/reports/releases/'.$release['id'];
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.ready', true);
        $case = DB::table('test_cases')->where('project_id', $this->project)->first();
        $this->withToken($this->author)->putJson($this->base().'/cases/'.$case->id, ['title' => 'Changed case', 'priority' => $case->priority, 'content' => json_decode($case->content, true)], ['If-Match' => '1'])->assertOk();
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.checks.fresh_scope', false)->assertJsonPath('data.ready', false);
    }

    public function test_force_closed_critical_is_not_silently_verified_and_preview_is_user_bound(): void
    {
        [$release, $run, $plan] = $this->releaseRun();
        foreach (DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get() as $item) {
            $this->observe($run, $item->id, 'pass');
        }
        $optional = DB::table('run_items')->where('test_run_id', $run)->where('required', false)->first();
        $this->observe($run, $optional->id, 'fail');
        $defect = $this->withToken($this->author)->postJson($this->base().'/defects', ['run_item_id' => $optional->id, 'title' => 'Unverified critical defect', 'description' => 'This closure is a documented business exception.', 'severity' => 'critical'])->assertCreated()->json('data.id');
        $this->withToken($this->author)->postJson($this->base().'/defects/'.$defect.'/transition', ['status' => 'closed', 'force' => true, 'confirmed' => true, 'reason' => 'Authorized exception without verification of the repair.'])->assertOk();
        $this->withToken($this->author)->getJson($this->base().'/reports/releases/'.$release['id'])->assertOk()->assertJsonPath('data.summary.required_pass_rate', 100)->assertJsonPath('data.checks.forced_critical', false)->assertJsonPath('data.ready', false);
        $preview = $this->preview($plan['id']);
        $this->withToken($this->reviewer)->postJson($this->base().'/planned-runs', ['title' => 'Stolen preview', 'preview_token' => $preview['preview_token']])->assertNotFound();
        $this->assertDatabaseCount('test_runs', 1);
    }

    public function test_invalid_list_shapes_and_oversized_variable_expansion_are_rejected(): void
    {
        $this->withToken($this->author)->postJson($this->base().'/cases', ['title' => 'Bad checklist', 'priority' => 'medium', 'content' => ['checklist' => ['named_step' => ['text' => 'Act', 'expected' => 'Observe', 'required' => true]]]])->assertUnprocessable();
        $this->withToken($this->author)->postJson($this->base().'/records/datasets', ['title' => 'Bad row shape', 'content' => ['columns' => [['key' => 'name', 'type' => 'string', 'required' => true]], 'rows' => ['named_row' => ['name' => 'value']]]])->assertUnprocessable();
        $case = $this->caseFixture();
        $stored = json_decode($case['content'], true);
        $stored['variables']['name'] = str_repeat('x', 3000);
        $this->withToken($this->author)->putJson($this->base().'/cases/'.$case['id'], ['title' => $case['title'], 'priority' => 'high', 'content' => $stored], ['If-Match' => '1'])->assertOk();
        $url = $this->base().'/cases/'.$case['id'].'/review';
        $this->withToken($this->author)->postJson($url, ['decision' => 'submit', 'version' => 2])->assertOk();
        $this->withToken($this->reviewer)->postJson($url, ['decision' => 'approve', 'version' => 2])->assertOk();
        $this->withToken($this->author)->postJson($this->base().'/runs', ['title' => 'Too long expansion', 'cases' => [['id' => $case['id'], 'version' => 2]]])->assertUnprocessable();
        $this->assertDatabaseCount('test_runs', 0);
    }

    public function test_saved_view_contains_treats_wildcards_as_literal_text(): void
    {
        $this->caseFixture('100% {{name}}');
        $this->caseFixture('1000 {{name}}');
        $view = $this->record('saved-views', ['visibility' => 'personal', 'columns' => ['priority'], 'conditions' => ['field' => 'title', 'operator' => 'contains', 'value' => '%']]);
        $this->withToken($this->author)->getJson($this->base().'/views/'.$view['id'].'/cases')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.title', '100% {{name}}');
    }

    public function test_date_values_are_rendered_as_solar_dates_but_preserve_iso_source_data(): void
    {
        $case = $this->caseFixture('Date {{day}}');
        $dataset = $this->record('datasets', ['columns' => [['key' => 'day', 'type' => 'date', 'required' => true]], 'rows' => [['day' => '2026-11-21']]]);
        $profile = $this->record('configuration-profiles', ['rows' => [['id' => (string) Str::uuid(), 'name' => 'Calendar', 'required' => true, 'dataset_id' => $dataset['id']]]]);
        $plan = $this->record('plans', ['case_ids' => [$case['id']], 'configuration_profile_id' => $profile['id']]);
        $preview = $this->preview($plan['id']);
        $this->assertSame('Date 1405/08/30', $preview['items'][0]['snapshot']['title']);
        $this->assertSame('2026-11-21', $preview['items'][0]['data']['values']['day']);
        $this->withToken($this->author)->postJson($this->base().'/records/environments', ['title' => 'Unsafe URL', 'content' => ['url' => 'https://name:secret@example.test']])->assertUnprocessable();
    }

    public function test_zero_threshold_never_approves_unexecuted_or_skipped_required_items_and_latest_baseline_uses_database_order(): void
    {
        [$release, $run, $plan] = $this->releaseRun(0);
        $url = $this->base().'/reports/releases/'.$release['id'];
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.checks.pass_rate', true)->assertJsonPath('data.checks.required_execution_complete', false)->assertJsonPath('data.ready', false);
        foreach (DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get() as $item) {
            $this->withToken($this->author)->postJson($this->base().'/runs/'.$run.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'skipped', 'checks' => ['skipped'], 'comment' => 'A controlled skip is not a completed required execution.'])->assertCreated();
        }
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.checks.required_execution_complete', false)->assertJsonPath('data.ready', false);
        foreach (DB::table('run_items')->where('test_run_id', $run)->where('required', true)->get() as $item) {
            $this->observe($run, $item->id, 'pass');
        }
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.ready', true);
        $preview = $this->preview($plan['id']);
        $next = $this->withToken($this->author)->postJson($this->base().'/planned-runs', ['title' => 'Next baseline on a slow-clock node', 'preview_token' => $preview['preview_token']])->assertCreated()->json('data.id');
        DB::table('test_runs')->where('id', $next)->update(['created_at' => now()->subDays(2)]);
        $this->withToken($this->author)->getJson($url)->assertOk()->assertJsonPath('data.summary.required_pass_rate', 0)->assertJsonPath('data.ready', false);
    }
}
