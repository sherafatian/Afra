<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentRules
{
    public function validate(string $project, array $values): void
    {
        $assignee = $values['assignee_id'] ?? null;
        $substitute = $values['substitute_id'] ?? null;
        if ($substitute !== null && ($assignee === null || (string) $assignee === (string) $substitute)) {
            throw ValidationException::withMessages(['substitute_id' => ['Choose a substitute different from the primary assigned tester.']]);
        }
        foreach (['assignee_id', 'substitute_id'] as $key) {
            $id = $values[$key] ?? null;
            if ($id === null) {
                continue;
            }$exists = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project)->where('users.id', $id)->where('users.active', true)->where(function ($q) {
                $q->whereNull('users.expires_at')->orWhere('users.expires_at', '>', now());
            })->exists();
            if (! $exists) {
                throw ValidationException::withMessages([$key => ['Choose an active, unexpired member of this project.']]);
            }
        }
    }

    public function changed(object $case, array $values): bool
    {
        foreach (['assignee_id', 'substitute_id'] as $key) {
            if (array_key_exists($key, $values) && (string) ($values[$key] ?? '') !== (string) ($case->{$key} ?? '')) {
                return true;
            }
        }

        return false;
    }
}
