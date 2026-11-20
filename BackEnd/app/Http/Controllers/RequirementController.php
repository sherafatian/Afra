<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequirementController extends Controller
{
    private function record(string $project, string $id, bool $lock = false): object
    {
        $query = DB::table('project_records')->where('project_id', $project)->where('kind', 'requirements')->where('id', $id);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);

        return $record;
    }

    private function audit(Request $request, string $project, string $id, string $action, array $details, ?string $section = null): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'section_id' => $section, 'actor_id' => $request->user()->id, 'action' => $action, 'entity_id' => $id, 'details' => json_encode(['actor_name' => $request->user()->name] + $details), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function index(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $v = $request->validate(['q' => 'nullable|string|max:200', 'archived' => 'sometimes|boolean', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $query = DB::table('project_records')->where('project_id', $project)->where('kind', 'requirements')->where('archived', $request->boolean('archived'));
        if (! empty($v['q'])) {
            $query->where('title', 'ilike', '%'.$v['q'].'%');
        }

        return response()->json(['data' => $query->orderByDesc('updated_at')->orderBy('id')->paginate($v['per_page'] ?? 25)]);
    }

    public function save(Request $request, string $project, ?string $requirement = null): JsonResponse
    {
        app(Access::class)->check($request, $project, $requirement ? 'requirements.update' : 'requirements.create');
        $v = $request->validate(['title' => 'required|string|max:250', 'content' => 'required|array:description,acceptance_criteria,product_version,priority', 'content.description' => 'required|string|max:20000', 'content.acceptance_criteria' => 'required|string|max:20000', 'content.product_version' => 'nullable|string|max:100', 'content.priority' => 'required|in:critical,high,medium,low', 'reason' => 'required|string|min:10|max:2000']);
        $id = $requirement ?? (string) Str::uuid();
        DB::transaction(function () use ($request, $project, $requirement, $v, $id) {
            $old = $requirement ? $this->record($project, $id, true) : null;
            if ($old) {
                abort_unless((string) $old->version === $request->header('If-Match'), 409, 'Requirement changed. Reload before saving.');
                abort_if($old->archived, 409, 'Archived requirement cannot be edited.');
            }
            $version = ($old->version ?? 0) + 1;
            $row = ['title' => $v['title'], 'content' => json_encode($v['content']), 'version' => $version, 'updated_at' => now()];
            if ($old) {
                DB::table('project_records')->where('id', $id)->update($row);
            } else {
                DB::table('project_records')->insert($row + ['id' => $id, 'kind' => 'requirements', 'project_id' => $project, 'created_at' => now()]);
            }
            DB::table('requirement_revisions')->insert(['requirement_id' => $id, 'version' => $version, 'author_id' => $request->user()->id, 'title' => $v['title'], 'content' => json_encode($v['content']), 'reason' => $v['reason'], 'created_at' => now()]);
            $this->audit($request, $project, $id, $requirement ? 'requirements.update' : 'requirements.create', ['version' => $version, 'reason' => $v['reason']]);
        });

        return response()->json(['data' => $this->record($project, $id)], $requirement ? 200 : 201);
    }

    public function archive(Request $request, string $project, string $requirement): JsonResponse
    {
        app(Access::class)->check($request, $project, 'requirements.delete');
        $v = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($request, $project, $requirement, $v) {
            $record = $this->record($project, $requirement, true);
            abort_unless((string) $record->version === $request->header('If-Match'), 409);
            abort_if($record->archived, 409);
            DB::table('project_records')->where('id', $requirement)->update(['archived' => true, 'updated_at' => now()]);
            $this->audit($request, $project, $requirement, 'requirements.archive', $v);
        });

        return response()->json(['data' => true]);
    }

    public function detail(Request $request, string $project, string $requirement): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $record = $this->record($project, $requirement);
        $record->content = json_decode($record->content, true);
        $record->revisions = DB::table('requirement_revisions')->where('requirement_id', $requirement)->orderByDesc('version')->get();
        $cases = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project), $request, $project)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('case_requirement_links as link')->whereColumn('link.test_case_id', 'test_cases.id')->whereColumn('link.case_version', 'test_cases.version')->where('link.requirement_id', $requirement))->orderBy('title')->paginate(25, ['*'], 'case_page');
        $cases->through(function ($case) use ($record, $request, $project) {
            $link = DB::table('case_requirement_links')->where('test_case_id', $case->id)->where('case_version', $case->version)->where('requirement_id', $record->id)->first();
            $compatible = ! $record->archived && ($link->requirement_version === $record->version || DB::table('requirement_compatibilities')->where('test_case_id', $case->id)->where('case_version', $case->version)->where('requirement_id', $record->id)->where('requirement_version', $record->version)->exists());
            // Only results from an item that actually froze this exact requirement revision count.
            $items = app(Access::class)->scope(DB::table('run_items'), $request, $project)->where('test_case_id', $case->id)->whereRaw("(snapshot->>'version')::integer = ?", [$case->version])->whereJsonContains('requirement_bindings', [['id' => $record->id, 'version' => $record->version]]);
            $latest = DB::table('test_results')->whereIn('run_item_id', (clone $items)->select('id'))->orderByDesc('created_at')->orderByDesc('id')->first();
            $state = ! $compatible ? 'stale' : ($latest?->outcome ?? (! $items->exists() ? 'not_planned' : 'not_run'));

            return ['id' => $case->id, 'title' => $case->title, 'version' => $case->version, 'author_id' => $case->author_id, 'review_status' => $case->status, 'linked_version' => $link->requirement_version, 'compatible' => $compatible, 'coverage' => $state];
        });
        $record->cases = $cases;
        $record->compatibilities = DB::table('requirement_compatibilities')->where('requirement_id', $requirement)->whereIn('test_case_id', $cases->getCollection()->pluck('id'))->orderByDesc('id')->get();

        return response()->json(['data' => $record]);
    }

    public function confirm(Request $request, string $project, string $requirement): JsonResponse
    {
        app(Access::class)->check($request, $project, 'requirements.confirm_compatibility');
        $v = $request->validate(['case_id' => 'required|uuid', 'case_version' => 'required|integer|min:1', 'requirement_version' => 'required|integer|min:1', 'reason' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($request, $project, $requirement, $v) {
            // Case -> requirement is the common lock order for case edit, review and run creation.
            $case = DB::table('test_cases')->where('project_id', $project)->where('id', $v['case_id'])->lockForUpdate()->first();
            abort_unless($case, 404);
            app(Access::class)->check($request, $project, 'requirements.confirm_compatibility', $case->section_id);
            $record = $this->record($project, $requirement, true);
            abort_if($record->archived || $record->version !== (int) $v['requirement_version'] || $case->version !== (int) $v['case_version'], 409, 'Source revision changed. Reload before confirming.');
            abort_if($case->author_id === $request->user()->id, 409, 'Case author cannot confirm compatibility.');
            abort_unless(DB::table('case_requirement_links')->where('test_case_id', $case->id)->where('case_version', $case->version)->where('requirement_id', $requirement)->exists(), 409, 'Case is not linked to this requirement.');
            $exists = DB::table('requirement_compatibilities')->where('test_case_id', $case->id)->where('case_version', $case->version)->where('requirement_id', $requirement)->where('requirement_version', $record->version)->exists();
            abort_if($exists, 409, 'Compatibility has already been confirmed.');
            DB::table('requirement_compatibilities')->insert(['test_case_id' => $case->id, 'case_version' => $case->version, 'requirement_id' => $requirement, 'requirement_version' => $record->version, 'reviewer_id' => $request->user()->id, 'reason' => $v['reason'], 'created_at' => now()]);
            $this->audit($request, $project, $requirement, 'requirements.confirm_compatibility', $v, $case->section_id);
        });

        return response()->json(['data' => true]);
    }
}
