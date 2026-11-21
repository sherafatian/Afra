<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\CaseViews;
use App\Services\DomainRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordController extends Controller
{
    public function options(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $v = $request->validate(['kind' => 'required|string', 'ids' => 'present|array|list|max:1000', 'ids.*' => 'uuid|distinct']);
        if ($v['kind'] === 'cases') {
            $query = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project), $request, $project);
        } else {
            abort_unless(in_array($v['kind'], DomainRecords::KINDS, true), 404);
            $query = DB::table('project_records')->where('project_id', $project)->where('kind', $v['kind']);
            if ($v['kind'] === 'saved-views') {
                $query->where(fn ($q) => $q->whereRaw("COALESCE(content->>'visibility', 'team') = 'team'")->orWhere('owner_id', $request->user()->id));
            }
        }

        return response()->json(['data' => $query->whereIn('id', $v['ids'])->get(['id', 'title', 'version'])]);
    }

    public function detail(Request $request, string $project, string $kind, string $record): JsonResponse
    {
        app(Access::class)->check($request, $project);
        abort_unless(in_array($kind, DomainRecords::KINDS, true), 404);
        if ($kind === 'requirements') {
            return app(RequirementController::class)->detail($request, $project, $record);
        }
        $value = app(DomainRecords::class)->find($request, $project, $kind, $record, false, true);
        $request->validate(['revision_page' => 'sometimes|integer|min:1|max:1000000']);
        $revisions = DB::table('project_record_revisions')->where('record_id', $record);
        $value->revision_total = (clone $revisions)->count();
        $value->revision_page = (int) $request->input('revision_page', 1);
        $value->revisions = $revisions->orderByDesc('version')->forPage($value->revision_page, 25)->get();
        if ($kind === 'shared-steps') {
            $value->approved_version = DB::table('project_record_revisions')->where('record_id', $record)->where('review_status', 'approved')->max('version');
        }

        return response()->json(['data' => $value]);
    }

    public function archive(Request $request, string $project, string $kind, string $record): JsonResponse
    {
        if ($kind === 'requirements') {
            return app(RequirementController::class)->archive($request, $project, $record);
        }
        app(Access::class)->check($request, $project);
        abort_unless(in_array($kind, DomainRecords::KINDS, true), 404);
        $v = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($request, $project, $kind, $record, $v) {
            $value = app(DomainRecords::class)->find($request, $project, $kind, $record, true);
            $permission = match ($kind) {
                'plans' => 'plans.delete', 'templates' => 'templates.manage', 'datasets' => 'datasets.update', 'configuration-profiles' => 'profiles.update', 'saved-views' => $value->content['visibility'] === 'personal' ? 'project.view' : 'views.manage', 'custom-fields' => 'fields.archive', 'shared-steps' => 'shared.archive', 'environments' => 'environments.manage', 'builds' => 'environments.manage', 'releases' => 'releases.manage',
            };
            app(Access::class)->check($request, $project, $permission);
            abort_unless((string) $value->version === $request->header('If-Match'), 409);
            DB::table('project_records')->where('id', $record)->update(['archived' => true, 'updated_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'actor_id' => $request->user()->id, 'action' => $kind.'.archive', 'entity_id' => $record, 'details' => json_encode($v), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => true]);
    }

    public function evaluate(Request $request, string $project, string $record): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $view = app(DomainRecords::class)->find($request, $project, 'saved-views', $record);
        $value = app(DomainRecords::class)->validate($request, $project, 'saved-views', $view->content, false);
        $query = app(CaseViews::class)->query($request, $project, $value['conditions']);

        $order = $value['order'] ?? 'title';
        $direction = $value['direction'] ?? 'asc';
        if ($order === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ".strtoupper($direction));
        } else {
            $query->orderBy('test_cases.'.$order, $direction);
        }
        $columns = array_values(array_unique(['id', ...($value['columns'] ?? ['title', 'priority', 'status', 'version'])]));
        $query->leftJoin('sections as section', 'section.id', '=', 'test_cases.section_id')->leftJoin('users as assignee', 'assignee.id', '=', 'test_cases.assignee_id')->select(array_map(fn ($column) => 'test_cases.'.$column, $columns));
        if (in_array('section_id', $columns, true)) {
            $query->addSelect('section.name as section_name');
        }
        if (in_array('assignee_id', $columns, true)) {
            $query->addSelect('assignee.name as assignee_name');
        }

        return response()->json(['data' => $query->orderBy('test_cases.id')->paginate(25)]);
    }
}
