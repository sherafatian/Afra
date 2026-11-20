<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Access
{
    public const ACTIONS = ['project.view', 'project.update', 'cases.create', 'cases.update', 'cases.delete', 'cases.assign', 'cases.export', 'cases.import', 'sections.manage', 'cases.review', 'shared.manage', 'templates.manage', 'views.manage', 'requirements.create', 'requirements.update', 'requirements.delete', 'plans.create', 'plans.update', 'plans.delete', 'runs.create', 'runs.execute', 'runs.pause', 'runs.retest', 'defects.create', 'defects.update', 'defects.transition', 'defects.close', 'defects.force_close', 'releases.manage', 'environments.manage', 'reports.read', 'reports.export', 'attachments.read', 'attachments.upload', 'attachments.delete', 'comments.write', 'members.manage', 'access.manage', 'fields.manage', 'system.users.manage', 'system.services.manage', 'sections.create', 'sections.update', 'sections.delete', 'shared.create', 'shared.update', 'shared.archive', 'shared.request_review', 'shared.update_cases', 'templates.create', 'templates.update', 'datasets.create', 'datasets.update', 'profiles.create', 'profiles.update', 'releases.create', 'releases.update', 'environments.create', 'environments.update', 'builds.create', 'builds.update', 'members.add', 'members.update', 'members.remove', 'roles.create', 'roles.update', 'roles.delete', 'groups.create', 'groups.update', 'grants.update', 'section_access.manage', 'fields.create', 'fields.update', 'fields.archive', 'cases.request_review', 'shared.approve', 'requirements.confirm_compatibility'];

    private const ALIASES = ['view' => 'project.view', 'projects.update' => 'project.update', 'files.upload' => 'attachments.upload', 'files.download' => 'attachments.read', 'cases.archive' => 'cases.delete', 'defects.manage' => 'defects.transition', 'defects.retest' => 'runs.retest', 'requirements.manage' => 'requirements.update', 'plans.manage' => 'plans.update', 'shared-steps.manage' => 'shared.manage', 'saved-views.manage' => 'views.manage', 'custom-fields.manage' => 'fields.manage', 'configuration-profiles.manage' => 'profiles.update', 'datasets.manage' => 'datasets.update', 'builds.manage' => 'builds.update'];

    private const LEGACY_EXPANSIONS = ['plans.manage' => ['plans.create', 'plans.update', 'plans.delete'], 'requirements.manage' => ['requirements.create', 'requirements.update', 'requirements.delete'], 'datasets.manage' => ['datasets.create', 'datasets.update'], 'builds.manage' => ['builds.create', 'builds.update'], 'configuration-profiles.manage' => ['profiles.create', 'profiles.update'], 'defects.manage' => ['defects.update', 'defects.transition', 'defects.close']];

    private const PARENTS = [
        'sections.create' => 'sections.manage', 'sections.update' => 'sections.manage', 'sections.delete' => 'sections.manage',
        'shared.create' => 'shared.manage', 'shared.update' => 'shared.manage', 'shared.archive' => 'shared.manage', 'shared.request_review' => 'shared.manage', 'shared.update_cases' => 'shared.manage', 'shared.approve' => 'shared.manage',
        'templates.create' => 'templates.manage', 'templates.update' => 'templates.manage',
        'releases.create' => 'releases.manage', 'releases.update' => 'releases.manage', 'environments.create' => 'environments.manage', 'environments.update' => 'environments.manage', 'builds.create' => 'environments.manage', 'builds.update' => 'environments.manage',
        'members.add' => 'members.manage', 'members.update' => 'members.manage', 'members.remove' => 'members.manage',
        'roles.create' => 'access.manage', 'roles.update' => 'access.manage', 'roles.delete' => 'access.manage', 'groups.create' => 'access.manage', 'groups.update' => 'access.manage', 'grants.update' => 'access.manage', 'section_access.manage' => 'access.manage',
        'fields.create' => 'fields.manage', 'fields.update' => 'fields.manage', 'fields.archive' => 'fields.manage',
    ];

    public static function canonical(string $action): string
    {
        return self::ALIASES[$action] ?? $action;
    }

    public static function defaults(string $role): array
    {
        return match ($role) {
            'admin' => array_values(array_filter(self::ACTIONS, fn ($key) => ! str_starts_with($key, 'system.'))),
            'tester' => ['project.view', 'runs.execute', 'runs.retest', 'defects.create', 'defects.update', 'defects.transition', 'defects.close', 'attachments.upload', 'comments.write', 'attachments.read', 'reports.read', 'reports.export'],
            'reviewer' => ['project.view', 'cases.review', 'shared.approve', 'requirements.confirm_compatibility', 'reports.read', 'reports.export', 'comments.write', 'attachments.read'],
            'custom' => ['project.view'],
            default => ['project.view', 'reports.read', 'reports.export', 'attachments.read'],
        };
    }

    public static function expanded(string $action): array
    {
        $keys = self::LEGACY_EXPANSIONS[$action] ?? [self::canonical($action)];
        $expanded = [];
        foreach ($keys as $key) {
            $expanded[] = $key;
            foreach (self::PARENTS as $child => $parent) {
                if ($parent === $key) {
                    $expanded[] = $child;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    private function grants(array $grants): array
    {
        $result = [];
        foreach ($grants as $key => $effect) {
            $effect = $effect === true ? 'allow' : ($effect === false ? 'deny' : $effect);
            if (! in_array($effect, ['allow', 'deny', 'inherit'], true)) {
                continue;
            }foreach (self::LEGACY_EXPANSIONS[$key] ?? [self::canonical($key)] as $canonical) {
                if (($result[$canonical] ?? null) !== 'deny') {
                    $result[$canonical] = $effect;
                }
            }
        }

        return $result;
    }

    private function context(Request $request, string $project): array
    {
        $key = 'afra.acl.'.$request->user()->id.'.'.$project;
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        $member = DB::table('project_members')->where('project_id', $project)->where('user_id', $request->user()->id)->first();
        abort_unless($member, 404);
        $sources = [$this->grants(json_decode($member->grants ?? '{}', true))];
        if ($member->role === 'custom' && $member->custom_role_id) {
            $role = DB::table('project_roles')->where('id', $member->custom_role_id)->where('project_id', $project)->where('active', true)->first();
            if ($role) {
                $sources[] = $this->grants(json_decode($role->grants, true));
            }
        }
        $groups = DB::table('permission_groups')->join('permission_group_members', 'permission_group_members.permission_group_id', '=', 'permission_groups.id')->where('permission_groups.project_id', $project)->where('permission_group_members.user_id', $request->user()->id)->where('active', true)->pluck('grants');
        foreach ($groups as $grants) {
            $sources[] = $this->grants(json_decode($grants, true));
        }
        $context = [$member, $sources];
        $request->attributes->set($key, $context);

        return $context;
    }

    private function allowed(string $action, object $member, array $sources): bool
    {
        if (str_starts_with($action, 'system.')) {
            return false;
        }
        $keys = [$action];
        if (isset(self::PARENTS[$action])) {
            $keys[] = self::PARENTS[$action];
        }
        $allowed = in_array($action, self::defaults($member->role), true);
        foreach ($sources as $grants) {
            foreach ($keys as $key) {
                $effect = $grants[$key] ?? 'inherit';
                if ($effect === 'deny') {
                    return false;
                }if ($effect === 'allow') {
                    $allowed = true;
                }
            }
        }

        return $allowed;
    }

    public function check(Request $r, string $project, string $action = 'view', ?string $section = null): object
    {
        $user = $r->user();
        abort_unless($user && $user->active && (! $user->expires_at || now()->lt($user->expires_at)), 401);
        abort_if($user->must_change_password, 403, 'Password change required.');
        $action = self::canonical($action);
        abort_unless(in_array($action, self::ACTIONS, true), 403);
        $projectKey = 'afra.project.'.$project;
        $p = $r->attributes->get($projectKey);
        if (! $p) {
            $p = DB::table('projects')->find($project);
            if ($p) {
                $r->attributes->set($projectKey, $p);
            }
        }
        abort_unless($p, 404);
        if ($section) {
            [$nodes] = $this->sectionMap($r, $project);
            abort_unless(isset($nodes[$section]), 404);
        }
        if (! $user->is_system_admin) {
            [$member,$sources] = $this->context($r, $project);
            abort_unless($this->allowed('project.view', $member, $sources), 404);
            abort_unless($this->allowed($action, $member, $sources), 403);
        }
        abort_if($p->archived && ! in_array($action, ['project.view', 'attachments.read', 'reports.read', 'reports.export', 'cases.export'], true), 409, 'Project is archived.');
        if ($section && ! $user->is_system_admin) {
            $rules = $this->resolvedSectionRules($r, $project, $section);
            abort_if(($rules['view'] ?? 'inherit') === 'deny', 404);
            $category = in_array($action, ['runs.execute', 'runs.retest'], true) ? 'execute' : 'edit';
            if (! in_array($action, ['project.view', 'attachments.read', 'reports.read', 'reports.export', 'cases.export'], true)) {
                abort_if(($rules[$category] ?? 'inherit') === 'deny', 403);
            }foreach ([$action, self::PARENTS[$action] ?? $action] as $key) {
                abort_if(($rules[$key] ?? 'inherit') === 'deny', 403);
            }
        }

        return $p;
    }

    private function sectionMap(Request $request, string $project): array
    {
        $key = 'afra.sections.'.$request->user()->id.'.'.$project;
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
            $local = [];
            foreach ($rules[$id] ?? [] as $rule) {
                if ($rule->effect === 'inherit') {
                    continue;
                }$key = in_array($rule->action, ['view', 'project.view'], true) ? 'view' : self::canonical($rule->action);
                if (($local[$key] ?? null) !== 'deny') {
                    $local[$key] = $rule->effect;
                }
            }$resolved = array_replace($resolved, $local);
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
