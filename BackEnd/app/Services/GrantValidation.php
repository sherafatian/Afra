<?php

namespace App\Services;

use Illuminate\Http\Request;

class GrantValidation
{
    public function validate(Request $request, string $project, array $next, array $previous = []): array
    {
        foreach ($next as $key => $effect) {
            abort_unless(in_array($key, Access::ACTIONS, true) && ! str_starts_with($key, 'system.') && in_array($effect, ['inherit', 'allow', 'deny'], true), 422, 'Invalid project permission.');
            if ($effect === 'allow') {
                $this->authority($request, $project, $key);
            }
        }
        foreach ($previous as $key => $effect) {
            if (($effect === 'deny' || $effect === false) && ($next[$key] ?? 'inherit') !== 'deny') {
                $this->removingRestrictions($request, $project, [$key => 'deny']);
            }
        }

        return array_filter($next, fn ($effect) => $effect !== 'inherit');
    }

    public function authority(Request $request, string $project, string $key, ?string $section = null): void
    {
        foreach (Access::expanded($key) as $action) {
            app(Access::class)->check($request, $project, $action, $section);
        }
    }

    public function removingRestrictions(Request $request, string $project, array $grants): void
    {
        foreach ($grants as $key => $effect) {
            if ($effect !== 'deny' && $effect !== false) {
                continue;
            }$actions = Access::canonical($key) === 'project.view' ? array_filter(Access::ACTIONS, fn ($action) => ! str_starts_with($action, 'system.')) : [$key];
            foreach ($actions as $action) {
                $this->authority($request, $project, $action);
            }
        }
    }
}
