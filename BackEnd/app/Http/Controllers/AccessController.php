<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\GrantValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AccessController extends Controller
{
    private function mutate(Request $request, string $project, callable $callback): mixed
    {
        return DB::transaction(function () use ($request, $project, $callback) {
            abort_unless(DB::table('projects')->where('id', $project)->lockForUpdate()->first(), 404);
            $request->attributes->remove('afra.project.'.$project);
            $request->attributes->remove('afra.acl.'.$request->user()->id.'.'.$project);
            $request->attributes->remove('afra.sections.'.$request->user()->id.'.'.$project);

            return $callback();
        });
    }

    private function audit(Request $request, string $project, string $action, string $entity, array $before, array $after, string $reason, array $users = [], ?string $section = null): void
    {
        if ($users) {
            DB::table('users')->whereIn('id', array_unique($users))->increment('acl_version');
        }
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'section_id' => $section, 'actor_id' => $request->user()->id, 'action' => $action, 'entity_id' => $entity, 'details' => json_encode(['actor_name' => $request->user()->name, 'before' => $before, 'after' => $after, 'reason' => $reason]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function catalog(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);

        return response()->json(['data' => array_values(array_filter(config('permissions'), fn ($permission) => ! str_starts_with($permission['key'], 'system.')))]);
    }

    public function capabilities(Request $request, string $project): JsonResponse
    {
        $v = $request->validate(['section_id' => 'sometimes|uuid']);
        $section = $v['section_id'] ?? null;
        app(Access::class)->check($request, $project, 'project.view', $section);
        $permissions = [];
        foreach (Access::ACTIONS as $key) {
            if (str_starts_with($key, 'system.')) {
                $permissions[$key] = (bool) $request->user()->is_system_admin;

                continue;
            }try {
                app(Access::class)->check($request, $project, $key, $section);
                $permissions[$key] = true;
            } catch (HttpExceptionInterface $error) {
                if (! in_array($error->getStatusCode(), [403, 404, 409], true)) {
                    throw $error;
                }$permissions[$key] = false;
            }
        }
        $grantable = [];
        foreach (Access::ACTIONS as $key) {
            $grantable[$key] = ! str_starts_with($key, 'system.') && ! in_array(false, array_map(fn ($action) => $permissions[$action] ?? false, Access::expanded($key)), true);
        }
        $restorable = $grantable;
        $restorable['project.view'] = ! in_array(false, array_intersect_key($permissions, array_flip(array_filter(Access::ACTIONS, fn ($key) => ! str_starts_with($key, 'system.')))), true);
        $defaults = [];
        foreach (['viewer', 'tester', 'reviewer', 'admin', 'custom'] as $role) {
            $defaults[$role] = Access::defaults($role);
        }

        return response()->json(['data' => ['permissions' => $permissions, 'grantable' => $grantable, 'restorable' => $restorable, 'role_defaults' => $defaults, 'acl_version' => $request->user()->acl_version]]);
    }

    public function groups(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $groups = DB::table('permission_groups')->where('project_id', $project)->orderBy('name')->get();
        $members = DB::table('permission_group_members')->whereIn('permission_group_id', $groups->pluck('id'))->get()->groupBy('permission_group_id');
        foreach ($groups as $group) {
            $group->grants = json_decode($group->grants, true);
            $group->members = ($members[$group->id] ?? collect())->pluck('user_id')->values();
        }

        return response()->json(['data' => $groups]);
    }

    public function saveGroup(Request $request, string $project, ?string $group = null): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'active' => 'required|boolean', 'grants' => 'present|array', 'members' => 'present|array|max:10000', 'members.*' => 'integer|distinct|exists:users,id', 'reason' => 'required|string|min:10|max:1000']);
        $id = $group ?? (string) Str::uuid();
        $this->mutate($request, $project, function () use ($request, $project, $group, $v, $id) {
            app(Access::class)->check($request, $project, $group ? 'groups.update' : 'groups.create');
            $old = $group ? DB::table('permission_groups')->where('id', $group)->where('project_id', $project)->lockForUpdate()->first() : null;
            if ($group) {
                abort_unless($old, 404);
                abort_unless((string) $old->version === $request->header('If-Match'), 409, 'The group changed. Reload before saving.');
            }$previous = $old ? json_decode($old->grants, true) : [];
            $grants = app(GrantValidation::class)->validate($request, $project, $v['grants'], $previous);
            $oldMembers = $old ? DB::table('permission_group_members')->where('permission_group_id', $id)->pluck('user_id')->all() : [];
            if ($old && $old->active && (! $v['active'] || array_diff($oldMembers, $v['members']))) {
                app(GrantValidation::class)->removingRestrictions($request, $project, $previous);
            }
            foreach ($v['members'] as $user) {
                abort_unless(DB::table('project_members')->where('project_id', $project)->where('user_id', $user)->exists(), 422, 'Every group member must belong to the project.');
            }
            $row = ['name' => $v['name'], 'active' => $v['active'], 'grants' => json_encode($grants), 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::table('permission_groups')->where('id', $id)->update($row);
            } else {
                DB::table('permission_groups')->insert($row + ['id' => $id, 'project_id' => $project, 'created_at' => now()]);
            }DB::table('permission_group_members')->where('permission_group_id', $id)->delete();
            foreach ($v['members'] as $user) {
                DB::table('permission_group_members')->insert(['permission_group_id' => $id, 'user_id' => $user]);
            }$this->audit($request, $project, $group ? 'groups.update' : 'groups.create', $id, ['grants' => $previous, 'members' => $oldMembers, 'active' => $old?->active], ['grants' => $grants, 'members' => $v['members'], 'active' => $v['active']], $v['reason'], array_merge($oldMembers, $v['members']));
        });

        return response()->json(['data' => DB::table('permission_groups')->find($id)], $group ? 200 : 201);
    }

    public function roles(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $rows = DB::table('project_roles')->where('project_id', $project)->orderBy('name')->get();
        foreach ($rows as $role) {
            $role->grants = json_decode($role->grants, true);
        }

        return response()->json(['data' => $rows]);
    }

    public function saveRole(Request $request, string $project, ?string $role = null): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'active' => 'required|boolean', 'grants' => 'present|array', 'reason' => 'required|string|min:10|max:1000']);
        $id = $role ?? (string) Str::uuid();
        $this->mutate($request, $project, function () use ($request, $project, $role, $v, $id) {
            app(Access::class)->check($request, $project, $role ? 'roles.update' : 'roles.create');
            $old = $role ? DB::table('project_roles')->where('id', $id)->where('project_id', $project)->lockForUpdate()->first() : null;
            if ($role) {
                abort_unless($old, 404);
                abort_unless((string) $old->version === $request->header('If-Match'), 409);
            }$previous = $old ? json_decode($old->grants, true) : [];
            $grants = app(GrantValidation::class)->validate($request, $project, $v['grants'], $previous);
            if ($old && $old->active && ! $v['active']) {
                app(GrantValidation::class)->removingRestrictions($request, $project, $previous);
            }abort_if(DB::table('project_roles')->where('project_id', $project)->where('name', $v['name'])->when($role, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'A role with this name exists.');
            $row = ['name' => $v['name'], 'active' => $v['active'], 'grants' => json_encode($grants), 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::table('project_roles')->where('id', $id)->update($row);
            } else {
                DB::table('project_roles')->insert($row + ['id' => $id, 'project_id' => $project, 'created_at' => now()]);
            }$users = DB::table('project_members')->where('project_id', $project)->where('custom_role_id', $id)->pluck('user_id')->all();
            $this->audit($request, $project, $role ? 'roles.update' : 'roles.create', $id, ['grants' => $previous, 'active' => $old?->active], ['grants' => $grants, 'active' => $v['active']], $v['reason'], $users);
        });

        return response()->json(['data' => DB::table('project_roles')->find($id)], $role ? 200 : 201);
    }

    public function member(Request $request, string $project): JsonResponse
    {
        $v = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role' => 'required|in:admin,tester,reviewer,viewer,custom', 'custom_role_id' => 'nullable|uuid|required_if:role,custom', 'grants' => 'sometimes|array', 'reason' => 'required|string|min:10|max:1000']);
        $this->mutate($request, $project, function () use ($request, $project, $v) {
            $old = DB::table('project_members')->where('project_id', $project)->where('user_id', $v['user_id'])->lockForUpdate()->first();
            app(Access::class)->check($request, $project, $old ? 'members.update' : 'members.add');
            if ($old) {
                abort_unless((string) $old->version === $request->header('If-Match'), 409, 'Membership changed. Reload before saving.');
            }if ($v['role'] === 'admin') {
                app(Access::class)->check($request, $project, 'access.manage');
            }
            foreach (Access::defaults($v['role']) as $action) {
                app(GrantValidation::class)->authority($request, $project, $action);
            }
            if ($v['role'] === 'custom') {
                $role = DB::table('project_roles')->where('id', $v['custom_role_id'])->where('project_id', $project)->where('active', true)->first();
                abort_unless($role, 422, 'Choose an active role from this project.');
                app(GrantValidation::class)->validate($request, $project, json_decode($role->grants, true));
            }
            if ($old && $old->custom_role_id && $old->custom_role_id !== ($v['custom_role_id'] ?? null)) {
                $oldGrants = DB::table('project_roles')->where('id', $old->custom_role_id)->value('grants');
                app(GrantValidation::class)->removingRestrictions($request, $project, json_decode($oldGrants ?? '{}', true));
            }
            $previous = json_decode($old->grants ?? '{}', true);
            $grants = $previous;
            if (array_key_exists('grants', $v)) {
                app(Access::class)->check($request, $project, 'grants.update');
                $grants = app(GrantValidation::class)->validate($request, $project, $v['grants'], $previous);
            }
            $row = ['role' => $v['role'], 'custom_role_id' => $v['role'] === 'custom' ? $v['custom_role_id'] : null, 'grants' => json_encode($grants), 'version' => ($old->version ?? 0) + 1];
            DB::table('project_members')->updateOrInsert(['project_id' => $project, 'user_id' => $v['user_id']], $row);
            $this->audit($request, $project, $old ? 'members.update' : 'members.add', (string) $v['user_id'], $old ? (array) $old : [], $row, $v['reason'], [$v['user_id']]);
        });

        return response()->json(['data' => true]);
    }

    public function sectionRules(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project, 'section_access.manage');
        $visible = app(Access::class)->visibleSections($request, $project);

        return response()->json(['data' => DB::table('section_rules')->whereIn('section_id', $visible)->get()]);
    }

    public function saveSectionRules(Request $request, string $project): JsonResponse
    {
        $v = $request->validate(['rules' => 'required|array|min:1|max:100', 'rules.*' => 'array:section_id,user_id,action,effect,version', 'rules.*.version' => 'sometimes|integer|min:1', 'rules.*.section_id' => 'required|uuid', 'rules.*.user_id' => 'required|integer|exists:users,id', 'rules.*.action' => 'required|string', 'rules.*.effect' => 'required|in:inherit,allow,deny', 'reason' => 'required|string|min:10|max:1000']);
        $v['rules'] = array_map(function ($rule) {
            if ($rule['action'] === 'project.view') {
                $rule['action'] = 'view';
            }

            return $rule;
        }, $v['rules']);
        $this->mutate($request, $project, function () use ($request, $project, $v) {
            $seen = [];
            $users = [];
            $before = [];
            foreach ($v['rules'] as $rule) {
                $key = $rule['section_id'].':'.$rule['user_id'].':'.$rule['action'];
                abort_if(isset($seen[$key]), 422, 'Duplicate section permission.');
                $seen[$key] = true;
                $users[] = $rule['user_id'];
                app(Access::class)->check($request, $project, 'section_access.manage', $rule['section_id']);
                abort_unless(DB::table('project_members')->where('project_id', $project)->where('user_id', $rule['user_id'])->exists(), 422, 'The user must belong to this project.');
                abort_unless(in_array($rule['action'], ['view', 'edit', 'execute', ...array_filter(Access::ACTIONS, fn ($key) => ! str_starts_with($key, 'system.'))], true), 422);
                $where = ['section_id' => $rule['section_id'], 'user_id' => $rule['user_id'], 'action' => $rule['action']];
                $old = DB::table('section_rules')->where($where)->first();
                $before[] = $old ? (array) $old : [];
                if ($old) {
                    abort_unless((int) ($rule['version'] ?? 0) === $old->version, 409, 'Section permissions changed. Reload before saving.');
                }
                if ($rule['effect'] === 'allow' || ($old?->effect === 'deny' && $rule['effect'] !== 'deny')) {
                    $actions = match ($rule['action']) {
                        'view','project.view' => array_filter(Access::ACTIONS, fn ($key) => ! str_starts_with($key, 'system.')),'execute' => ['runs.execute', 'runs.retest'],'edit' => array_filter(Access::ACTIONS, fn ($key) => ! str_starts_with($key, 'system.') && ! in_array($key, ['project.view', 'attachments.read', 'reports.read', 'reports.export', 'cases.export', 'runs.execute', 'runs.retest'], true)),default => [$rule['action']]
                    };
                    foreach ($actions as $action) {
                        app(GrantValidation::class)->authority($request, $project, $action, $rule['section_id']);
                    }
                }
                DB::table('section_rules')->updateOrInsert($where, ['effect' => $rule['effect'], 'version' => ($old->version ?? 0) + 1]);
                $this->audit($request, $project, 'section_access.manage', $rule['section_id'], $old ? (array) $old : [], $rule, $v['reason'], [], $rule['section_id']);
            }
            DB::table('users')->whereIn('id', array_unique($users))->increment('acl_version');
        });

        return response()->json(['data' => true]);
    }

    public function directory(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $permitted = false;
        foreach (['members.manage', 'members.add', 'members.update'] as $action) {
            try {
                app(Access::class)->check($request, $project, $action);
                $permitted = true;
                break;
            } catch (HttpExceptionInterface $error) {
                if ($error->getStatusCode() !== 403) {
                    throw $error;
                }
            }
        }abort_unless($permitted, 403);

        $v = $request->validate(['q' => 'required|string|min:2|max:100']);

        return response()->json(['data' => DB::table('users')->where('active', true)->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->where(function ($q) use ($v) {
            $q->where('name', 'ilike', '%'.$v['q'].'%')->orWhere('email', 'ilike', '%'.$v['q'].'%');
        })->select('id', 'name', 'email')->orderBy('name')->limit(25)->get()]);
    }

    public function memberOptions(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project);
        $v = $request->validate(['ids' => 'present|array|max:10000', 'ids.*' => 'integer|distinct']);

        return response()->json(['data' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project)->whereIn('users.id', $v['ids'])->select('users.id', 'users.name', 'users.email')->get()]);
    }
}
