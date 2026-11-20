<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->is_system_admin, 403);
    }

    private function serialize(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'active', 'is_system_admin', 'must_change_password', 'account_version', 'temporary_password_expires_at']) + [
            'expires_on' => $user->expires_at ? $user->expires_at->copy()->timezone('Asia/Tehran')->subDay()->toDateString() : null,
        ];
    }

    private function values(Request $request, ?string $id = null): array
    {
        $allowed = ['name', 'email', 'active', 'is_system_admin', 'expires_on', 'reason'];
        if ($id === null) {
            $allowed = [...$allowed, 'password', 'must_change_password'];
        }
        abort_if(array_diff(array_keys($request->all()), $allowed), 422, 'Unsupported account fields.');
        if ($request->has('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }
        $values = $request->validate([
            'name' => ($id ? 'sometimes' : 'required').'|string|max:200',
            'email' => [($id ? 'sometimes' : 'required'), 'email', 'max:250', Rule::unique('users', 'email')->ignore($id)],
            'active' => 'sometimes|boolean', 'is_system_admin' => 'sometimes|boolean',
            'expires_on' => 'sometimes|nullable|date_format:Y-m-d',
            'reason' => 'required|string|min:10|max:1000',
            ...($id ? [] : ['password' => 'required|string|min:12|max:128', 'must_change_password' => 'sometimes|boolean']),
        ]);
        if (array_key_exists('expires_on', $values)) {
            $values['expires_at'] = $values['expires_on'] === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', $values['expires_on'], 'Asia/Tehran')->addDay()->utc();
            abort_if($values['expires_at'] && $values['expires_at']->lte(now()), 422, 'Account expiry must include today or a future Tehran date.');
            unset($values['expires_on']);
        }
        unset($values['reason']);

        return $values;
    }

    private function audit(Request $request, User $user, string $action, ?array $before): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => null, 'actor_id' => $request->user()->id, 'action' => $action, 'entity_id' => (string) $user->id, 'details' => json_encode(['reason' => $request->input('reason'), 'before' => $before, 'after' => $this->serialize($user)]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->admin($request);
        $v = $request->validate(['q' => 'nullable|string|max:100', 'active' => 'sometimes|in:0,1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $query = User::query()->select('id', 'name', 'email', 'active', 'expires_at', 'is_system_admin', 'must_change_password', 'account_version', 'temporary_password_expires_at');
        if (! empty($v['q'])) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$v['q'].'%')->orWhere('email', 'ilike', '%'.$v['q'].'%'));
        }
        if (isset($v['active'])) {
            $query->where('active', $v['active'] === '1');
        }
        $page = $query->orderBy('id')->paginate($v['per_page'] ?? 25);
        $page->through(fn (User $user) => $this->serialize($user));

        return response()->json(['data' => $page]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->admin($request);
        $values = $this->values($request);
        $user = DB::transaction(function () use ($request, $values) {
            $user = new User;
            $user->forceFill($values + ['must_change_password' => true])->save();
            $user->refresh();
            $this->audit($request, $user, 'users.create', null);

            return $user;
        });

        return response()->json(['data' => $this->serialize($user)], 201);
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $this->admin($request);
        $values = $this->values($request, $user);
        $result = DB::transaction(function () use ($request, $user, $values) {
            $admins = User::where('is_system_admin', true)->orderBy('id')->lockForUpdate()->get();
            $record = User::lockForUpdate()->findOrFail($user);
            abort_unless((string) $record->account_version === $request->header('If-Match'), 409, 'Account changed. Reload before saving.');
            $before = $this->serialize($record);
            $restrict = ($values['active'] ?? $record->active) === false || ($values['is_system_admin'] ?? $record->is_system_admin) === false || (array_key_exists('expires_at', $values) && $values['expires_at'] !== null);
            if ($restrict) {
                abort_if($record->id === $request->user()->id, 409, 'You cannot restrict your own account.');
                abort_if($record->is_system_admin && ! $admins->contains(fn ($other) => $other->id !== $record->id && $other->active && ! $other->expires_at), 409, 'Keep one permanent active system administrator.');
            }
            $record->forceFill($values + ['auth_version' => $record->auth_version + 1, 'account_version' => $record->account_version + 1])->save();
            DB::table('auth_sessions')->where('user_id', $record->id)->update(['revoked_at' => now()]);
            $this->audit($request, $record, 'users.update', $before);

            return $record;
        });

        return response()->json(['data' => $this->serialize($result)]);
    }

    public function reset(Request $request, string $user): JsonResponse
    {
        $this->admin($request);
        abort_if((string) $request->user()->id === $user, 409, 'Use your own password-change form.');
        $values = $request->validate(['password' => 'required|string|min:12|max:128', 'reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($request, $user, $values) {
            $record = User::lockForUpdate()->findOrFail($user);
            abort_unless((string) $record->account_version === $request->header('If-Match'), 409, 'Account changed. Reload before resetting.');
            $before = $this->serialize($record);
            $record->forceFill(['password' => $values['password'], 'must_change_password' => true, 'temporary_password_expires_at' => now()->addHours(24), 'auth_version' => $record->auth_version + 1, 'account_version' => $record->account_version + 1])->save();
            DB::table('auth_sessions')->where('user_id', $record->id)->update(['revoked_at' => now()]);
            $this->audit($request, $record, 'users.reset_password', $before);
        });

        return response()->json(['data' => true]);
    }
}
