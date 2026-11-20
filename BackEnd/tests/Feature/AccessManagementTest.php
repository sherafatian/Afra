<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-acl-private.pem', $private);
        file_put_contents('/tmp/afra-acl-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-acl-private.pem', 'afra.public_key' => '/tmp/afra-acl-public.pem']);
        $admin = $this->account(true);
        $this->adminToken = $this->login($admin);
        $this->project = $this->withToken($this->adminToken)->postJson('/api/v1/projects', ['name' => 'Access test'])->assertCreated()->json('data.id');
    }

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create(['password' => 'TestOnlyPassword123!']);
        $user->forceFill(['is_system_admin' => $admin, 'must_change_password' => false])->save();

        return $user;
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'TestOnlyPassword123!'])->assertOk()->json('data.access_token');
    }

    private function member(User $user, array $grants = [], string $role = 'viewer'): void
    {
        DB::table('project_members')->insert(['project_id' => $this->project, 'user_id' => $user->id, 'role' => $role, 'grants' => json_encode($grants)]);
    }

    private function base(): string
    {
        return '/api/v1/projects/'.$this->project;
    }

    private function caseBody(): array
    {
        return ['title' => 'Permission probe', 'priority' => 'medium', 'content' => ['checklist' => [['text' => 'Observe', 'expected' => 'Expected outcome', 'required' => true]]]];
    }

    public function test_category_deny_wins_over_a_direct_child_allow(): void
    {
        $user = $this->account();
        $this->member($user, ['sections.create' => 'allow']);
        $group = (string) Str::uuid();
        DB::table('permission_groups')->insert(['id' => $group, 'project_id' => $this->project, 'name' => 'No tree edits', 'active' => true, 'grants' => json_encode(['sections.manage' => 'deny']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_group_members')->insert(['permission_group_id' => $group, 'user_id' => $user->id]);
        $token = $this->login($user);
        $this->withToken($token)->postJson($this->base().'/sections', ['name' => 'Denied'])->assertForbidden();
        $permissions = $this->withToken($token)->getJson($this->base().'/me/capabilities')->assertOk()->json('data.permissions');
        $this->assertFalse($permissions['sections.create']);
    }

    public function test_limited_manager_cannot_grant_a_category_containing_a_denied_child(): void
    {
        $user = $this->account();
        $this->member($user, ['sections.delete' => 'deny'], 'admin');
        $token = $this->login($user);
        $this->withToken($token)->postJson($this->base().'/groups', ['name' => 'Escalation attempt', 'active' => true, 'grants' => ['sections.manage' => 'allow'], 'members' => [$user->id], 'reason' => 'A parent allow must not bypass a denied child.'])->assertForbidden();
        $this->assertDatabaseCount('permission_groups', 0);
    }

    public function test_disabling_a_deny_group_cannot_restore_rights_beyond_the_manager(): void
    {
        $user = $this->account();
        $this->member($user, ['access.manage' => 'allow', 'cases.create' => 'allow']);
        $group = (string) Str::uuid();
        DB::table('permission_groups')->insert(['id' => $group, 'project_id' => $this->project, 'name' => 'Restriction', 'active' => true, 'grants' => json_encode(['cases.create' => 'deny']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_group_members')->insert(['permission_group_id' => $group, 'user_id' => $user->id]);
        $token = $this->login($user);
        $this->withToken($token)->putJson($this->base().'/groups/'.$group, ['name' => 'Restriction', 'active' => false, 'grants' => ['cases.create' => 'deny'], 'members' => [$user->id], 'reason' => 'Trying to remove a restriction by deactivation.'], ['If-Match' => '1'])->assertForbidden();
        $this->assertDatabaseHas('permission_groups', ['id' => $group, 'active' => true, 'version' => 1]);
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertForbidden();
    }

    public function test_revoking_a_group_allow_applies_to_an_existing_jwt(): void
    {
        $user = $this->account();
        $this->member($user);
        $group = $this->withToken($this->adminToken)->postJson($this->base().'/groups', ['name' => 'Authors', 'active' => true, 'grants' => ['cases.create' => 'allow'], 'members' => [$user->id], 'reason' => 'Grant case authoring to this training member.'])->assertCreated()->json('data.id');
        $token = $this->login($user);
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertCreated();
        $this->withToken($this->adminToken)->putJson($this->base().'/groups/'.$group, ['name' => 'Authors', 'active' => false, 'grants' => ['cases.create' => 'allow'], 'members' => [$user->id], 'reason' => 'Revoke authoring without waiting for JWT expiry.'], ['If-Match' => '1'])->assertOk();
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertForbidden();
    }

    public function test_custom_role_and_optimistic_membership_writes(): void
    {
        $user = $this->account();
        $role = $this->withToken($this->adminToken)->postJson($this->base().'/roles', ['name' => 'Case author', 'active' => true, 'grants' => ['cases.create' => 'allow'], 'reason' => 'Create a deliberately narrow author role.'])->assertCreated()->json('data.id');
        $this->withToken($this->adminToken)->postJson($this->base().'/members', ['user_id' => $user->id, 'role' => 'custom', 'custom_role_id' => $role, 'reason' => 'Assign the approved narrow project role.'])->assertOk();
        $token = $this->login($user);
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertCreated();
        $permissions = $this->withToken($token)->getJson($this->base().'/me/capabilities')->assertOk()->json('data.permissions');
        $this->assertTrue($permissions['cases.create']);
        $this->assertFalse($permissions['cases.update']);
        $this->assertFalse($permissions['system.users.manage']);
        $this->withToken($this->adminToken)->postJson($this->base().'/members', ['user_id' => $user->id, 'role' => 'viewer', 'reason' => 'Remove author role and retain read access.'], ['If-Match' => '1'])->assertOk();
        $this->withToken($this->adminToken)->postJson($this->base().'/members', ['user_id' => $user->id, 'role' => 'tester', 'reason' => 'A stale administrator form must not overwrite.'], ['If-Match' => '1'])->assertConflict();
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertForbidden();
    }

    public function test_project_view_deny_also_hides_project_selector_and_other_actions(): void
    {
        $user = $this->account();
        $this->member($user, ['project.view' => 'deny', 'cases.create' => 'allow']);
        $token = $this->login($user);
        $this->withToken($token)->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.total', 0);
        $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertNotFound();
    }

    public function test_project_groups_cannot_grant_system_permissions(): void
    {
        $this->withToken($this->adminToken)->postJson($this->base().'/groups', ['name' => 'Invalid system group', 'active' => true, 'grants' => ['system.users.manage' => 'allow'], 'members' => [], 'reason' => 'Project groups never confer system authority.'])->assertUnprocessable();
        $this->assertDatabaseCount('permission_groups', 0);
    }

    public function test_assignment_only_permission_preserves_approved_content_and_existing_runs(): void
    {
        $reviewer = $this->account(true);
        $reviewerToken = $this->login($reviewer);
        $assignee = $this->account();
        $substitute = $this->account();
        $manager = $this->account();
        $this->member($assignee, [], 'tester');
        $this->member($substitute, [], 'tester');
        $this->member($manager, ['cases.assign' => 'allow']);
        $case = $this->withToken($this->adminToken)->postJson($this->base().'/cases', $this->caseBody())->assertCreated()->json('data');
        $this->withToken($this->adminToken)->postJson($this->base().'/cases/'.$case['id'].'/review', ['decision' => 'submit', 'version' => 1])->assertOk();
        $this->withToken($reviewerToken)->postJson($this->base().'/cases/'.$case['id'].'/review', ['decision' => 'approve', 'version' => 1])->assertOk();
        $before = $this->withToken($this->adminToken)->postJson($this->base().'/runs', ['title' => 'Before assignment', 'cases' => [['id' => $case['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $managerToken = $this->login($manager);
        $body = ['assignee_id' => $assignee->id, 'substitute_id' => $substitute->id, 'reason' => 'Assign the primary tester and a distinct substitute.'];
        $this->withToken($managerToken)->postJson($this->base().'/cases/'.$case['id'].'/assignment', $body, ['If-Match' => '1'])->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.version', 1)->assertJsonPath('data.assignment_version', 2);
        $this->assertDatabaseCount('case_revisions', 1);
        $oldItem = DB::table('run_items')->where('test_run_id', $before)->first();
        $this->assertNull($oldItem->assignee_id);
        $this->assertNull($oldItem->substitute_id);
        $this->withToken($managerToken)->putJson($this->base().'/cases/'.$case['id'], $this->caseBody(), ['If-Match' => '1'])->assertForbidden();
        $this->withToken($managerToken)->postJson($this->base().'/cases/'.$case['id'].'/assignment', $body, ['If-Match' => '1'])->assertConflict();
        $after = $this->withToken($this->adminToken)->postJson($this->base().'/runs', ['title' => 'After assignment', 'cases' => [['id' => $case['id'], 'version' => 1]]])->assertCreated()->json('data.id');
        $item = DB::table('run_items')->where('test_run_id', $after)->first();
        $this->assertSame($assignee->id, $item->assignee_id);
        $this->assertSame($substitute->id, $item->substitute_id);
        $this->withToken($this->login($substitute))->postJson($this->base().'/runs/'.$after.'/items/'.$item->id.'/results', ['request_id' => (string) Str::uuid(), 'outcome' => 'pass', 'checks' => ['pass']])->assertCreated();
    }

    public function test_content_edit_does_not_bypass_assignment_permission(): void
    {
        $writer = $this->account();
        $tester = $this->account();
        $this->member($writer, ['cases.create' => 'allow', 'cases.update' => 'allow']);
        $this->member($tester, [], 'tester');
        $token = $this->login($writer);
        $case = $this->withToken($token)->postJson($this->base().'/cases', $this->caseBody())->assertCreated()->json('data');
        $body = $this->caseBody() + ['assignee_id' => $tester->id, 'assignment_version' => 1];
        $this->withToken($token)->putJson($this->base().'/cases/'.$case['id'], $body, ['If-Match' => '1'])->assertForbidden();
        $this->assertDatabaseHas('test_cases', ['id' => $case['id'], 'version' => 1, 'assignee_id' => null]);
        DB::table('project_members')->where('project_id', $this->project)->where('user_id', $writer->id)->update(['grants' => json_encode(['cases.create' => 'allow', 'cases.update' => 'allow', 'cases.assign' => 'allow'])]);
        $this->withToken($token)->putJson($this->base().'/cases/'.$case['id'], $body, ['If-Match' => '1'])->assertOk()->assertJsonPath('data.assignment_version', 2);
        $tester->active = false;
        $tester->save();
        $this->withToken($token)->postJson($this->base().'/cases/'.$case['id'].'/assignment', ['assignee_id' => $tester->id, 'substitute_id' => null, 'reason' => 'An inactive account cannot be newly assigned.'], ['If-Match' => '2'])->assertUnprocessable();
    }

    public function test_evidence_read_can_be_denied_without_hiding_the_project(): void
    {
        $user = $this->account();
        $this->member($user, ['attachments.read' => 'deny']);
        $file = $this->withToken($this->adminToken)->post($this->base().'/files', ['file' => UploadedFile::fake()->createWithContent('proof.txt', 'Synthetic proof')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $token = $this->login($user);
        $this->withToken($token)->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.total', 1);
        $this->withToken($token)->getJson($this->base().'/files/'.$file)->assertForbidden();
    }

    public function test_section_rules_have_conflict_detection_and_canonical_visibility_keys(): void
    {
        $user = $this->account();
        $this->member($user);
        $section = $this->withToken($this->adminToken)->postJson($this->base().'/sections', ['name' => 'Controlled section'])->assertCreated()->json('data.id');
        $rule = ['section_id' => $section, 'user_id' => $user->id, 'action' => 'view', 'effect' => 'deny'];
        $this->withToken($this->adminToken)->putJson($this->base().'/section-access', ['rules' => [$rule], 'reason' => 'Restrict this section for the training member.'])->assertOk();
        $this->withToken($this->adminToken)->putJson($this->base().'/section-access', ['rules' => [array_replace($rule, ['effect' => 'inherit', 'version' => 1])], 'reason' => 'Restore inherited visibility after review.'])->assertOk();
        $this->withToken($this->adminToken)->putJson($this->base().'/section-access', ['rules' => [array_replace($rule, ['version' => 1])], 'reason' => 'A stale rule edit must not overwrite a newer one.'])->assertConflict();
        $this->withToken($this->adminToken)->putJson($this->base().'/section-access', ['rules' => [array_replace($rule, ['action' => 'project.view', 'effect' => 'inherit', 'version' => 2])], 'reason' => 'Normalize the project-view alias to section visibility.'])->assertOk();
        $this->assertDatabaseCount('section_rules', 1);
        $this->assertDatabaseHas('section_rules', ['section_id' => $section, 'user_id' => $user->id, 'action' => 'view', 'effect' => 'inherit', 'version' => 3]);
    }

    public function test_removing_a_project_view_deny_cannot_restore_another_users_broader_role(): void
    {
        $manager = $this->account();
        $administrator = $this->account();
        $this->member($manager, ['access.manage' => 'allow']);
        $this->member($administrator, [], 'admin');
        $group = (string) Str::uuid();
        DB::table('permission_groups')->insert(['id' => $group, 'project_id' => $this->project, 'name' => 'Hidden project', 'active' => true, 'grants' => json_encode(['project.view' => 'deny']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_group_members')->insert(['permission_group_id' => $group, 'user_id' => $administrator->id]);
        $managerToken = $this->login($manager);
        $this->withToken($managerToken)->putJson($this->base().'/groups/'.$group, ['name' => 'Hidden project', 'active' => false, 'grants' => ['project.view' => 'deny'], 'members' => [$administrator->id], 'reason' => 'Visibility restoration must not restore authority beyond the manager.'], ['If-Match' => '1'])->assertForbidden();
        $this->assertDatabaseHas('permission_groups', ['id' => $group, 'active' => true, 'version' => 1]);
    }

    public function test_empty_member_search_and_empty_selected_options_are_valid(): void
    {
        $this->withToken($this->adminToken)->getJson($this->base().'/members?q=&per_page=100&active_only=0')->assertOk()->assertJsonPath('data.total', 1);
        $this->withToken($this->adminToken)->postJson($this->base().'/member-options', ['ids' => []])->assertOk()->assertJsonCount(0, 'data');
    }
}
