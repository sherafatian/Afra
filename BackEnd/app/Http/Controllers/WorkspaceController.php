<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\AssignmentRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    private function access(Request $r, string $project, string $action = 'view', ?string $section = null): object
    {
        return app(Access::class)->check($r, $project, $action, $section);
    }

    private function audit(Request $r, ?string $project, string $action, ?string $entity, array $details = []): void
    {
        $section = null;
        if ($project && $entity && Str::isUuid($entity)) {
            $domain = explode('.', $action)[0];
            $section = match ($domain) {
                'cases' => DB::table('test_cases')->where('project_id', $project)->where('id', $entity)->value('section_id'),
                'sections' => $entity,
                'results' => DB::table('run_items')->join('test_results', 'test_results.run_item_id', '=', 'run_items.id')->where('test_results.id', $entity)->value('run_items.section_id'),
                'defects' => DB::table('run_items')->join('defects', 'defects.run_item_id', '=', 'run_items.id')->where('defects.id', $entity)->where('defects.project_id', $project)->value('run_items.section_id'),
                default => null,
            };
        }
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'section_id' => $section, 'actor_id' => $r->user()->id, 'action' => $action, 'entity_id' => $entity, 'details' => json_encode(['actor_name' => $r->user()->name] + $details), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function reply(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    private function record(string $table, string $id, string $project): object
    {
        $row = DB::table($table)->where('id', $id)->where('project_id', $project)->first();
        abort_unless($row, 404);

        return $row;
    }

    public function me(Request $r): JsonResponse
    {
        return $this->reply($r->user()->only(['id', 'name', 'email', 'profile', 'is_system_admin', 'must_change_password']));
    }

    public function projects(Request $r): JsonResponse
    {
        $q = DB::table('projects');
        if (! $r->user()->is_system_admin) {
            $members = DB::table('project_members as m')->select('m.project_id')->where('m.user_id', $r->user()->id)
                ->whereRaw("COALESCE(m.grants->>'project.view','inherit') NOT IN ('deny','false')")
                ->whereRaw("COALESCE(m.grants->>'view','inherit') NOT IN ('deny','false')")
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')->from('project_roles as role')->whereColumn('role.id', 'm.custom_role_id')->where('role.active', true)->where(function ($q) {
                        $q->whereRaw("role.grants->>'project.view' IN ('deny','false')")->orWhereRaw("role.grants->>'view' IN ('deny','false')");
                    });
                })
                ->whereNotExists(function ($q) use ($r) {
                    $q->selectRaw('1')->from('permission_groups as g')->join('permission_group_members as gm', 'gm.permission_group_id', '=', 'g.id')->whereColumn('g.project_id', 'm.project_id')->where('gm.user_id', $r->user()->id)->where('g.active', true)->where(function ($q) {
                        $q->whereRaw("g.grants->>'project.view' IN ('deny','false')")->orWhereRaw("g.grants->>'view' IN ('deny','false')");
                    });
                });
            $q->whereIn('id', $members);
        }

        return $this->reply($q->orderBy('name')->paginate(25));
    }

    public function createProject(Request $r): JsonResponse
    {
        abort_unless($r->user()->is_system_admin, 403);
        $v = $r->validate(['name' => 'required|string|max:200', 'description' => 'nullable|string|max:5000']);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($r, $v, $id) {
            DB::table('projects')->insert($v + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_members')->insert(['project_id' => $id, 'user_id' => $r->user()->id, 'role' => 'admin']);
            $this->audit($r, $id, 'projects.create', $id);
        });

        return $this->reply(DB::table('projects')->find($id), 201);
    }

    public function sections(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);

        $visible = app(Access::class)->visibleSections($r, $project);
        $nodes = DB::table('sections')->where('project_id', $project)->whereIn('id', $visible)->orderBy('name')->get();
        foreach ($nodes as $node) {
            if ($node->parent_id && ! in_array($node->parent_id, $visible, true)) {
                $node->parent_id = null;
            }
        }

        return $this->reply($nodes);
    }

    public function createSection(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project, 'sections.create');
        $v = $r->validate(['name' => 'required|string|max:200', 'parent_id' => 'nullable|uuid']);
        $depth = 1;
        $parent = $v['parent_id'] ?? null;
        if ($parent) {
            $this->access($r, $project, 'sections.create', $parent);
        }
        while ($parent) {
            $node = $this->record('sections', $parent, $project);
            $parent = $node->parent_id;
            abort_if(++$depth > 8, 422, 'Maximum section depth is eight.');
        }$id = (string) Str::uuid();
        DB::table('sections')->insert($v + ['id' => $id, 'project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($r, $project, 'sections.create', $id);

        return $this->reply(DB::table('sections')->find($id), 201);
    }

    public function cases(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);
        $q = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project), $r, $project);
        if ($r->filled('q')) {
            $q->where('title', 'ilike', '%'.mb_substr($r->string('q'), 0, 200).'%');
        }if ($r->filled('section_id')) {
            $q->where('section_id', $r->input('section_id'));
        }

        return $this->reply($q->orderByDesc('updated_at')->paginate(min(100, max(1, (int) $r->input('per_page', 25)))));
    }

    public function caseDetail(Request $r, string $project, string $case): JsonResponse
    {
        $this->access($r, $project);
        $c = $this->record('test_cases', $case, $project);
        $c->content = json_decode($c->content, true);
        app(Access::class)->check($r, $project, 'view', $c->section_id);
        $c->revisions = DB::table('case_revisions')->where('test_case_id', $case)->orderByDesc('version')->get();

        return $this->reply($c);
    }

    private function caseInput(Request $r, string $project): array
    {
        $v = $r->validate(['title' => 'required|string|max:250', 'section_id' => 'nullable|uuid', 'assignee_id' => 'nullable|integer', 'substitute_id' => 'nullable|integer', 'assignment_version' => 'sometimes|integer|min:1', 'priority' => 'required|in:critical,high,medium,low', 'content' => 'required|array:preconditions,expected,checklist,tags,type,estimate', 'content.preconditions' => 'nullable|string|max:10000', 'content.expected' => 'nullable|string|max:10000', 'content.type' => 'nullable|in:functional,regression,smoke,security,performance', 'content.estimate' => 'nullable|integer|min:1|max:1440', 'content.tags' => 'nullable|array|max:30', 'content.tags.*' => 'string|max:60', 'content.checklist' => 'required|array|min:1|max:200', 'content.checklist.*' => 'array:text,expected,required', 'content.checklist.*.text' => 'required|string|max:3000', 'content.checklist.*.expected' => 'required|string|max:3000', 'content.checklist.*.required' => 'required|boolean']);
        if (! empty($v['section_id'])) {
            $this->record('sections', $v['section_id'], $project);
        }
        foreach (['assignee_id', 'substitute_id'] as $key) {
            if (isset($v[$key])) {
                $v[$key] = (int) $v[$key];
            }
        }

        return $v;
    }

    public function saveCase(Request $r, string $project, ?string $case = null): JsonResponse
    {
        $operation = $case ? 'cases.update' : 'cases.create';
        $this->access($r, $project, $operation);
        $v = $this->caseInput($r, $project);
        $id = $case ?? (string) Str::uuid();
        DB::transaction(function () use ($r, $project, $case, $id, $v, $operation) {
            $old = $case ? DB::table('test_cases')->where('id', $id)->where('project_id', $project)->lockForUpdate()->first() : null;
            if ($case) {
                abort_unless($old, 404);
                $this->access($r, $project, $operation, $old->section_id);
                abort_unless((string) $old->version === $r->header('If-Match'), 409, 'The test case changed. Reload before saving.');
            }
            $section = array_key_exists('section_id', $v) ? $v['section_id'] : ($old->section_id ?? null);
            $this->access($r, $project, $operation, $section);
            $changed = $old && app(AssignmentRules::class)->changed($old, $v);
            $effective = ['assignee_id' => array_key_exists('assignee_id', $v) ? $v['assignee_id'] : ($old->assignee_id ?? null), 'substitute_id' => array_key_exists('substitute_id', $v) ? $v['substitute_id'] : ($old->substitute_id ?? null)];
            if (! $old || $changed) {
                app(AssignmentRules::class)->validate($project, $effective);
            }
            $needsAssignment = $old ? $changed : (! empty($v['substitute_id']) || (! empty($v['assignee_id']) && (string) $v['assignee_id'] !== (string) $r->user()->id));
            if ($needsAssignment) {
                $this->access($r, $project, 'cases.assign', $old->section_id ?? $section);
                $this->access($r, $project, 'cases.assign', $section);
            }
            if ($changed) {
                abort_unless((int) ($v['assignment_version'] ?? 0) === $old->assignment_version, 409, 'The assignment changed. Reload before saving.');
            }
            $version = ($old->version ?? 0) + 1;
            $assignmentVersion = ($old->assignment_version ?? 1) + ($changed ? 1 : 0);
            $row = $v;
            $row['assignment_version'] = $assignmentVersion;
            $row['author_id'] = $r->user()->id;
            $row['content'] = json_encode($v['content']);
            $row += ['status' => 'draft', 'version' => $version, 'updated_at' => now()];
            if ($old) {
                DB::table('test_cases')->where('id', $id)->update($row);
            } else {
                DB::table('test_cases')->insert($row + ['id' => $id, 'project_id' => $project, 'created_at' => now()]);
            }
            $snapshot = $v + ['section_id' => $section, 'assignee_id' => $old->assignee_id ?? null, 'substitute_id' => $old->substitute_id ?? null];
            $snapshot['assignment_version'] = $assignmentVersion;
            DB::table('case_revisions')->insert(['test_case_id' => $id, 'version' => $version, 'author_id' => $r->user()->id, 'snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($r, $project, $case ? 'cases.update' : 'cases.create', $id, ['version' => $version]);
        });

        return $this->reply(DB::table('test_cases')->find($id), $case ? 200 : 201);
    }

    public function review(Request $r, string $project, string $case): JsonResponse
    {
        $v = $r->validate(['decision' => 'required|in:submit,approve,reject', 'version' => 'required|integer|min:1', 'reason' => 'required_if:decision,reject|nullable|string|min:10|max:2000']);
        $this->access($r, $project, $v['decision'] === 'submit' ? 'cases.request_review' : 'cases.review');
        DB::transaction(function () use ($r, $project, $case, $v) {
            $c = DB::table('test_cases')->where('id', $case)->where('project_id', $project)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless((int) $v['version'] === $c->version, 409, 'The revision changed. Review the current content before deciding.');
            $this->access($r, $project, $v['decision'] === 'submit' ? 'cases.request_review' : 'cases.review', $c->section_id);
            $rev = DB::table('case_revisions')->where('test_case_id', $case)->where('version', $c->version)->first();
            if ($v['decision'] === 'submit') {
                abort_unless($c->status === 'draft', 409);
                $status = 'pending';
            } else {
                abort_unless($c->status === 'pending', 409);
                abort_if($rev->author_id === $r->user()->id, 409, 'The author cannot review their own revision.');
                $status = $v['decision'] === 'approve' ? 'approved' : 'draft';
            }DB::table('test_cases')->where('id', $case)->update(['status' => $status, 'updated_at' => now()]);
            DB::table('case_revisions')->where('id', $rev->id)->update(['status' => $status, 'reviewer_id' => $v['decision'] === 'submit' ? null : $r->user()->id, 'reason' => $v['reason'] ?? null, 'updated_at' => now()]);
            $this->audit($r, $project, 'cases.review.'.$v['decision'], $case, ['version' => $c->version]);
        });

        return $this->reply(true);
    }

    public function records(Request $r, string $project, string $kind): JsonResponse
    {
        $this->access($r, $project);
        $this->kind($kind);

        return $this->reply(DB::table('project_records')->where('project_id', $project)->where('kind', $kind)->orderByDesc('updated_at')->paginate(25));
    }

    private function kind(string $kind): void
    {
        abort_unless(in_array($kind, ['requirements', 'plans', 'releases', 'environments', 'builds', 'templates', 'datasets', 'shared-steps', 'saved-views', 'custom-fields', 'configuration-profiles'], true), 404);
    }

    public function saveRecord(Request $r, string $project, string $kind, ?string $record = null): JsonResponse
    {
        $this->kind($kind);
        $prefix = match ($kind) {
            'shared-steps' => 'shared','configuration-profiles' => 'profiles','custom-fields' => 'fields',default => $kind
        };
        $operation = $kind === 'saved-views' ? 'views.manage' : $prefix.'.'.($record ? 'update' : 'create');
        $this->access($r, $project, $operation);
        $v = $r->validate(['title' => 'required|string|max:250', 'content' => 'required|array']);
        $id = $record ?? (string) Str::uuid();
        DB::transaction(function () use ($r, $project, $kind, $record, $v, $id) {
            $old = $record ? DB::table('project_records')->where('id', $id)->where('project_id', $project)->where('kind', $kind)->lockForUpdate()->first() : null;
            if ($record) {
                abort_unless($old, 404);
                abort_unless((string) $old->version === $r->header('If-Match'), 409);
            }$row = ['title' => $v['title'], 'content' => json_encode($v['content']), 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::table('project_records')->where('id', $id)->update($row);
            } else {
                DB::table('project_records')->insert($row + ['id' => $id, 'project_id' => $project, 'kind' => $kind, 'created_at' => now()]);
            }$this->audit($r, $project, $kind.'.save', $id, ['version' => $row['version']]);
        });

        return $this->reply(DB::table('project_records')->find($id), $record ? 200 : 201);
    }

    public function runs(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);

        $items = app(Access::class)->scope(DB::table('run_items'), $r, $project)->select('test_run_id');

        return $this->reply(DB::table('test_runs')->where('project_id', $project)->whereIn('id', $items)->orderByDesc('created_at')->paginate(25));
    }

    public function createRun(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project, 'runs.create');
        $v = $r->validate(['title' => 'required|string|max:250', 'cases' => 'required|array|min:1|max:1000', 'cases.*' => 'required|array:id,version', 'cases.*.id' => 'required|uuid|distinct', 'cases.*.version' => 'required|integer|min:1']);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($r, $project, $v, $id) {
            DB::table('test_runs')->insert(['id' => $id, 'project_id' => $project, 'title' => $v['title'], 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($v['cases'] as $entry) {
                $c = DB::table('test_cases')->where('project_id', $project)->where('id', $entry['id'])->lockForUpdate()->first();
                abort_unless($c, 404);
                $this->access($r, $project, 'runs.create', $c->section_id);
                abort_unless($c->status === 'approved' && $c->version === $entry['version'], 409, 'Only the current approved revision can enter a run. Refresh the selection.');
                DB::table('run_items')->insert(['id' => (string) Str::uuid(), 'test_run_id' => $id, 'test_case_id' => $c->id, 'section_id' => $c->section_id, 'snapshot' => json_encode(['case_id' => $c->id, 'version' => $c->version, 'title' => $c->title, 'assignment_version' => $c->assignment_version, 'assignee_id' => $c->assignee_id, 'substitute_id' => $c->substitute_id, 'content' => json_decode($c->content, true)]), 'assignee_id' => $c->assignee_id, 'substitute_id' => $c->substitute_id, 'created_at' => now(), 'updated_at' => now()]);
            }$this->audit($r, $project, 'runs.create', $id);
        });

        return $this->reply(DB::table('test_runs')->find($id), 201);
    }

    public function runDetail(Request $r, string $project, string $run): JsonResponse
    {
        $this->access($r, $project);
        $data = $this->record('test_runs', $run, $project);
        $items = app(Access::class)->scope(DB::table('run_items')->where('test_run_id', $run), $r, $project)->get();
        abort_if($items->isEmpty(), 404);
        foreach ($items as $item) {
            $item->snapshot = json_decode($item->snapshot, true);
            $item->results = DB::table('test_results')->leftJoin('users', 'users.id', '=', 'test_results.actor_id')->where('test_results.run_item_id', $item->id)->select('test_results.*', 'users.name as actor_current_name')->orderBy('test_results.created_at')->get();
        }$data->items = $items;

        return $this->reply($data);
    }

    public function result(Request $r, string $project, string $run, string $item): JsonResponse
    {
        $this->access($r, $project, 'runs.execute');
        $this->record('test_runs', $run, $project);
        $i = DB::table('run_items')->where('id', $item)->where('test_run_id', $run)->first();
        abort_unless($i, 404);
        $this->access($r, $project, 'runs.execute', $i->section_id);
        $member = DB::table('project_members')->where('project_id', $project)->where('user_id', $r->user()->id)->first();
        abort_unless($r->user()->is_system_admin || $member?->role === 'admin' || ($i->assignee_id === $r->user()->id || $i->substitute_id === $r->user()->id), 403, 'This item is assigned to another tester.');
        $v = $r->validate(['evidence_ids' => 'sometimes|array|max:20', 'evidence_ids.*' => 'uuid|distinct', 'request_id' => 'required|uuid', 'outcome' => 'required|in:pass,fail,blocked,skipped', 'checks' => 'required|array|max:200', 'checks.*' => 'required|in:pass,fail,skipped,not_run', 'comment' => 'nullable|string|max:5000']);
        $steps = json_decode($i->snapshot, true)['content']['checklist'];
        abort_unless(count($v['checks']) === count($steps), 422, 'Every checklist item needs a result.');
        if ($v['outcome'] === 'pass') {
            foreach ($steps as $index => $step) {
                abort_unless($v['checks'][$index] === 'pass' || (! $step['required'] && $v['checks'][$index] === 'skipped' && ! empty($v['comment'])), 422, 'Required checklist items must pass.');
            }
        } elseif ($v['outcome'] === 'fail') {
            abort_unless(in_array('fail', $v['checks'], true), 422, 'Select the failed checklist item.');
        } else {
            abort_unless(! empty($v['comment']), 422, 'Explain why this execution was blocked or skipped.');
        }$id = (string) Str::uuid();
        DB::transaction(function () use ($r, $project, $item, $v, $id) {
            DB::table('run_items')->where('id', $item)->lockForUpdate()->first();
            $existing = DB::table('test_results')->where('request_id', $v['request_id'])->first();
            if ($existing) {
                abort_unless($existing->run_item_id === $item && $existing->actor_id === $r->user()->id && $existing->outcome === $v['outcome'] && json_decode($existing->checks, true) === $v['checks'] && ($existing->comment ?? null) === ($v['comment'] ?? null) && (json_decode($existing->evidence_ids ?? '[]', true) ?? []) === ($v['evidence_ids'] ?? []), 409, 'Idempotency conflict.');

                return;
            }
            foreach ($v['evidence_ids'] ?? [] as $fileId) {
                $file = DB::table('attachments')->where('id', $fileId)->where('project_id', $project)->lockForUpdate()->first();
                abort_unless($file && $file->owner_id === $r->user()->id && $file->entity_type === 'run_item' && $file->entity_id === $item, 422, 'Evidence must be your unrecorded upload for this execution item.');
            }
            DB::table('test_results')->insert(['id' => $id, 'run_item_id' => $item, 'evidence_ids' => json_encode($v['evidence_ids'] ?? []), 'actor_id' => $r->user()->id, 'actor_name' => $r->user()->name, 'request_id' => $v['request_id'], 'outcome' => $v['outcome'], 'checks' => json_encode($v['checks']), 'comment' => $v['comment'] ?? null, 'created_at' => now()->format('Y-m-d H:i:s.u'), 'updated_at' => now()]);
            if (! empty($v['evidence_ids'])) {
                DB::table('attachments')->whereIn('id', $v['evidence_ids'])->update(['entity_type' => 'result', 'entity_id' => $id]);
            }
            $this->audit($r, $project, 'results.record', $id, ['item_id' => $item, 'outcome' => $v['outcome']]);
        });

        return $this->reply(DB::table('test_results')->where('request_id', $v['request_id'])->first(), 201);
    }

    public function defects(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);

        $items = app(Access::class)->scope(DB::table('run_items'), $r, $project)->select('id');

        return $this->reply(DB::table('defects')->where('project_id', $project)->whereIn('run_item_id', $items)->orderByDesc('updated_at')->paginate(25));
    }

    public function createDefect(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project, 'defects.create');
        $v = $r->validate(['run_item_id' => 'required|uuid', 'title' => 'required|string|max:250', 'description' => 'required|string|max:10000', 'severity' => 'required|in:critical,high,medium,low']);
        $item = DB::table('run_items')->join('test_runs', 'test_runs.id', '=', 'run_items.test_run_id')->where('run_items.id', $v['run_item_id'])->where('test_runs.project_id', $project)->first();
        abort_unless($item, 404);
        $this->access($r, $project, 'defects.create', $item->section_id);
        $latest = DB::table('test_results')->where('run_item_id', $v['run_item_id'])->orderByDesc('created_at')->first();
        abort_unless($latest && $latest->outcome === 'fail', 409, 'A recorded failed result is required.');
        $id = (string) Str::uuid();
        DB::table('defects')->insert($v + ['id' => $id, 'source_result_id' => $latest->id, 'project_id' => $project, 'history' => json_encode([['actor' => $r->user()->name, 'action' => 'created', 'description' => $v['description'], 'at' => now()->toIso8601String()]]), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($r, $project, 'defects.create', $id);

        return $this->reply(DB::table('defects')->find($id), 201);
    }

    public function transitionDefect(Request $r, string $project, string $defect): JsonResponse
    {
        $v = $r->validate(['status' => 'required|in:in_fix,resolved,open,closed', 'force' => 'sometimes|boolean', 'confirmed' => 'sometimes|boolean', 'reason' => 'required|string|min:10|max:1000']);
        $forced = ($v['force'] ?? false) && $v['status'] === 'closed';
        $this->access($r, $project, $forced ? 'defects.force_close' : ($v['status'] === 'closed' ? 'defects.close' : 'defects.transition'));
        DB::transaction(function () use ($r, $project, $defect, $v, $forced) {
            $d = DB::table('defects')->where('id', $defect)->where('project_id', $project)->lockForUpdate()->first();
            abort_unless($d, 404);
            $origin = DB::table('run_items')->find($d->run_item_id);
            $this->access($r, $project, $forced ? 'defects.force_close' : ($v['status'] === 'closed' ? 'defects.close' : 'defects.transition'), $origin->section_id);
            abort_if($d->status === $v['status'], 409);
            if ($v['status'] === 'closed') {
                if ($forced) {
                    abort_unless($v['confirmed'] ?? false, 422, 'Confirm unverified closure.');
                } else {
                    abort_unless($d->status === 'resolved' && $d->resolved_at, 409, 'Resolve the defect before closing.');
                    $latestRetest = DB::table('test_results')->join('retest_links', 'retest_links.run_item_id', '=', 'test_results.run_item_id')->where('retest_links.defect_id', $defect)->orderByDesc('test_results.created_at')->select('test_results.outcome', 'test_results.created_at')->first();
                    $verified = $latestRetest && $latestRetest->outcome === 'pass' && CarbonImmutable::parse($latestRetest->created_at)->gt(CarbonImmutable::parse($d->resolved_at));
                    abort_unless($verified, 409, 'Record a Pass in a linked retest after the fix was marked resolved.');
                }
            }$history = json_decode($d->history, true);
            $history[] = ['actor' => $r->user()->name, 'action' => $forced ? 'force_closed' : $v['status'], 'description' => $v['reason'], 'at' => now()->toIso8601String()];
            DB::table('defects')->where('id', $defect)->update(['status' => $v['status'], 'resolved_at' => $v['status'] === 'resolved' ? now()->format('Y-m-d H:i:s.uP') : $d->resolved_at, 'closure_type' => $v['status'] === 'closed' ? ($forced ? 'forced' : 'verified') : null, 'verified_at' => $v['status'] === 'closed' && ! $forced ? now() : null, 'history' => json_encode($history), 'updated_at' => now()]);
            $this->audit($r, $project, $forced ? 'defects.force_close' : 'defects.transition', $defect, ['reason' => $v['reason'], 'status' => $v['status']]);
        });

        return $this->reply(DB::table('defects')->find($defect));
    }

    public function auditEvents(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);
        $q = app(Access::class)->scope(DB::table('audit_events')->where('project_id', $project), $r, $project);
        $visibleRuns = app(Access::class)->scope(DB::table('run_items'), $r, $project)->selectRaw('CAST(test_run_id AS varchar)');
        $q->where(function ($q) use ($visibleRuns) {
            $q->where('action', 'not like', 'runs.%')->orWhereIn('entity_id', $visibleRuns);
        });
        foreach (['action', 'actor_id', 'entity_id'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }if ($r->filled('from')) {
            $q->where('created_at', '>=', $r->date('from'));
        }if ($r->filled('to')) {
            $q->where('created_at', '<=', $r->date('to'));
        }

        return $this->reply($q->orderByDesc('created_at')->paginate(20));
    }

    public function dashboard(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);
        $access = app(Access::class);
        $cases = $access->scope(DB::table('test_cases')->where('project_id', $project), $r, $project);
        $items = $access->scope(DB::table('run_items'), $r, $project);
        $defects = DB::table('defects')->where('project_id', $project)->whereIn('run_item_id', (clone $items)->select('id'));
        $counts = [
            'test_cases' => (clone $cases)->count(),
            'test_runs' => DB::table('test_runs')->where('project_id', $project)->whereIn('id', (clone $items)->select('test_run_id'))->count(),
            'defects' => (clone $defects)->count(),
            'sections' => count($access->visibleSections($r, $project)),
            'pending_review' => (clone $cases)->where('status', 'pending')->count(),
            'open_critical' => (clone $defects)->where('severity', 'critical')->where('status', '!=', 'closed')->count(),
            'forced_closed' => (clone $defects)->where('closure_type', 'forced')->count(),
        ];

        return $this->reply($counts);
    }

    public function retest(Request $r, string $project, string $defect): JsonResponse
    {
        $this->access($r, $project, 'runs.retest');
        $d = $this->record('defects', $defect, $project);
        abort_unless($d->status === 'resolved', 409, 'Mark the defect resolved before retesting.');
        $source = DB::table('run_items')->find($d->run_item_id);
        $this->access($r, $project, 'runs.retest', $source->section_id);
        $m = DB::table('project_members')->where('project_id', $project)->where('user_id', $r->user()->id)->first();
        abort_unless($r->user()->is_system_admin || $m?->role === 'admin' || ($source->assignee_id === $r->user()->id || $source->substitute_id === $r->user()->id), 403);
        $run = (string) Str::uuid();
        $item = (string) Str::uuid();
        DB::transaction(function () use ($r, $project, $defect, $d, $source, $run, $item) {
            DB::table('test_runs')->insert(['id' => $run, 'project_id' => $project, 'title' => 'Retest: '.$d->title, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('run_items')->insert(['id' => $item, 'test_run_id' => $run, 'test_case_id' => $source->test_case_id, 'section_id' => $source->section_id, 'snapshot' => $source->snapshot, 'assignee_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('retest_links')->insert(['id' => (string) Str::uuid(), 'defect_id' => $defect, 'run_item_id' => $item, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($r, $project, 'defects.retest', $defect, ['run_id' => $run, 'item_id' => $item]);
        });

        return $this->reply(['run_id' => $run, 'item_id' => $item], 201);
    }

    public function members(Request $r, string $project): JsonResponse
    {
        $this->access($r, $project);

        $v = $r->validate(['q' => 'nullable|string|max:200', 'active_only' => 'sometimes|boolean', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $query = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project);
        if ($v['active_only'] ?? false) {
            $query->where('users.active', true)->where(function ($q) {
                $q->whereNull('users.expires_at')->orWhere('users.expires_at', '>', now());
            });
        }
        if (! empty($v['q'])) {
            $query->where(function ($q) use ($v) {
                $q->where('users.name', 'ilike', '%'.$v['q'].'%')->orWhere('users.email', 'ilike', '%'.$v['q'].'%');
            });
        }

        return $this->reply($query->select('users.id', 'users.name', 'users.email', 'users.active', 'project_members.role', 'project_members.custom_role_id', 'project_members.version as membership_version', 'project_members.grants')->orderBy('users.name')->paginate($v['per_page'] ?? 25));
    }

    public function profile(Request $r): JsonResponse
    {
        $v = $r->validate(['name' => 'required|string|max:200', 'mobile' => 'nullable|string|regex:/^(\+98|0)?9[0-9]{9}$/', 'bio' => 'nullable|string|max:500']);
        $allowed = ['name', 'mobile', 'bio'];
        abort_if(array_diff(array_keys($r->all()), $allowed), 422, 'Some fields cannot be changed in self-service.');
        $u = $r->user();
        $p = $u->profile ?? [];
        $p['mobile'] = $v['mobile'] ?? null;
        $p['bio'] = $v['bio'] ?? null;
        $u->forceFill(['name' => $v['name'], 'profile' => $p])->save();

        return $this->reply($u->only(['id', 'name', 'email', 'profile']));
    }
}
