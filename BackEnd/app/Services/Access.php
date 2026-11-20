<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Access
{
    public const ACTIONS = ['view', 'projects.update', 'sections.manage', 'cases.create', 'cases.update', 'cases.assign', 'cases.review', 'cases.export', 'cases.import', 'cases.archive', 'requirements.manage', 'plans.manage', 'releases.manage', 'environments.manage', 'builds.manage', 'templates.manage', 'datasets.manage', 'shared-steps.manage', 'saved-views.manage', 'custom-fields.manage', 'configuration-profiles.manage', 'runs.create', 'runs.execute', 'runs.retest', 'defects.create', 'defects.manage', 'defects.retest', 'defects.force_close', 'members.manage', 'access.manage', 'files.upload', 'files.download', 'reports.read'];

    public function check(Request $r, string $project, string $action = 'view', ?string $section = null): object
    {
        $p = DB::table('projects')->find($project);
        abort_unless($p, 404);
        $u = $r->user();
        $m = DB::table('project_members')->where('project_id', $project)->where('user_id', $u->id)->first();
        abort_unless($u->is_system_admin || $m, 404);
        abort_if($p->archived && $action !== 'view' && $action !== 'files.download' && $action !== 'reports.read', 409, 'Project is archived.');
        if ($section) {
            abort_unless(DB::table('sections')->where('id', $section)->where('project_id', $project)->exists(), 404);
        }
        if ($u->is_system_admin) {
            return $p;
        }
        $sources = [json_decode($m->grants ?? '{}', true)];
        $groups = DB::table('permission_groups')->join('permission_group_members', 'permission_group_members.permission_group_id', '=', 'permission_groups.id')->where('permission_groups.project_id', $project)->where('permission_group_members.user_id', $u->id)->where('active', true)->pluck('grants');
        foreach ($groups as $g) {
            $sources[] = json_decode($g, true);
        }
        $allow = $action === 'view' || $m->role === 'admin' || ($m->role === 'reviewer' && $action === 'cases.review') || ($m->role === 'tester' && in_array($action, ['runs.execute', 'runs.retest', 'defects.create', 'defects.retest', 'files.upload', 'files.download', 'reports.read'], true));
        foreach ($sources as $grants) {
            $effect = $grants[$action] ?? 'inherit';
            abort_if($effect === 'deny', 403);
            if ($effect === 'allow') {
                $allow = true;
            }
        }
        abort_unless($allow, 403);
        if ($section) {
            $rules = $this->resolvedSectionRules($r, $project, $section);
            abort_if(($rules['view'] ?? 'inherit') === 'deny', 404);
            $category = in_array($action, ['runs.execute', 'runs.retest', 'defects.retest'], true) ? 'execute' : 'edit';
            if ($action !== 'view' && ! in_array($action, ['reports.read', 'files.download'], true)) {
                abort_if(($rules[$category] ?? 'inherit') === 'deny', 403);
            }
            abort_if(($rules[$action] ?? 'inherit') === 'deny', $action === 'view' ? 404 : 403);
        }

        return $p;
    }

    private function sectionMap(Request $request, string $project): array
    {
        $key = 'afra.sections.'.$project;
        if (! $request->attributes->has($key)) {
            $sections = DB::table('sections')->where('project_id', $project)->get()->keyBy('id');
            $rules = DB::table('section_rules')->where('user_id', $request->user()->id)->whereIn('section_id', $sections->keys())->get()->groupBy('section_id');
            $request->attributes->set($key, [$sections, $rules]);
        }

        return $request->attributes->get($key);
    }

    private function resolvedSectionRules(Request $request, string $project, string $section): array
    {
        [$sections,$rules] = $this->sectionMap($request, $project);
        $path = [];
        $node = $section;
        while ($node) {
            abort_unless(isset($sections[$node]), 404);
            abort_if(in_array($node, $path, true) || count($path) >= 8, 409, 'Invalid section tree.');
            $path[] = $node;
            $node = $sections[$node]->parent_id;
        }
        $resolved = [];
        foreach (array_reverse($path) as $id) {
            foreach ($rules[$id] ?? [] as $rule) {
                if ($rule->effect !== 'inherit') {
                    $resolved[$rule->action] = $rule->effect;
                }
            }
        }

        return $resolved;
    }

    public function visibleSections(Request $request, string $project): array
    {
        $this->check($request, $project);
        [$sections] = $this->sectionMap($request, $project);
        if ($request->user()->is_system_admin) {
            return $sections->keys()->all();
        }

        return $sections->keys()->filter(fn ($id) => ($this->resolvedSectionRules($request, $project, $id)['view'] ?? 'inherit') !== 'deny')->values()->all();
    }

    public function scope(Builder $query, Request $request, string $project, string $column = 'section_id'): Builder
    {
        $visible = $this->visibleSections($request, $project);

        return $query->where(function ($q) use ($column, $visible) {
            $q->whereNull($column)->orWhereIn($column, $visible);
        });
    }
}
