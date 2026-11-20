<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function admin(Request $r): void
    {
        abort_unless($r->user()->is_system_admin, 403);
    }

    public function index(Request $r): JsonResponse
    {
        $this->admin($r);

        return response()->json(['data' => User::select('id', 'name', 'email', 'active', 'expires_at', 'is_system_admin', 'must_change_password')->orderBy('id')->paginate(25)]);
    }

    public function create(Request $r): JsonResponse
    {
        $this->admin($r);
        $r->merge(['email' => mb_strtolower((string) $r->input('email'))]);
        $v = $r->validate(['name' => 'required|string|max:200', 'email' => 'required|email|max:250|unique:users,email', 'password' => 'required|string|min:12|max:128', 'active' => 'sometimes|boolean', 'expires_at' => 'nullable|date|after:now', 'must_change_password' => 'sometimes|boolean']);
        $v['email'] = mb_strtolower($v['email']);
        $u = new User;
        $u->forceFill($v + ['must_change_password' => true])->save();

        return response()->json(['data' => $u->only(['id', 'name', 'email', 'active', 'expires_at', 'must_change_password'])], 201);
    }

    public function update(Request $r, string $user): JsonResponse
    {
        $this->admin($r);
        $v = $r->validate(['name' => 'sometimes|string|max:200', 'active' => 'sometimes|boolean', 'expires_at' => 'nullable|date|after:now', 'is_system_admin' => 'sometimes|boolean']);
        DB::transaction(function () use ($r, $user, $v) {
            $admins = User::where('is_system_admin', true)->orderBy('id')->lockForUpdate()->get();
            $u = User::lockForUpdate()->findOrFail($user);
            $restrict = isset($v['active']) && ! $v['active'] || isset($v['is_system_admin']) && ! $v['is_system_admin'] || ! empty($v['expires_at']);
            if ($restrict) {
                abort_if($u->id === $r->user()->id, 409, 'You cannot restrict your own account.');
                $other = $admins->filter(fn ($a) => $a->id !== $u->id && $a->active && ! $a->expires_at);
                abort_if($u->is_system_admin && ! $other->count(), 409, 'Keep one permanent active system administrator.');
            }$u->forceFill($v + ['auth_version' => $u->auth_version + 1])->save();
            DB::table('auth_sessions')->where('user_id', $u->id)->update(['revoked_at' => now()]);
        });

        return response()->json(['data' => true]);
    }

    public function reset(Request $r, string $user): JsonResponse
    {
        $this->admin($r);
        $v = $r->validate(['password' => 'required|string|min:12|max:128', 'reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($user, $v) {
            $u = User::lockForUpdate()->findOrFail($user);
            $u->forceFill(['password' => Hash::make($v['password']), 'must_change_password' => true, 'auth_version' => $u->auth_version + 1])->save();
            DB::table('auth_sessions')->where('user_id', $u->id)->update(['revoked_at' => now()]);
        });

        return response()->json(['data' => true]);
    }
}
