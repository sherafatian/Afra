<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseViews
{
    public function validate(Request $request, string $project, array $node, int $depth = 0, int &$count = 0): void
    {
        abort_if($depth > 3 || ++$count > 40, 422, 'View exceeds its nesting or condition limit.');
        if (isset($node['rules'])) {
            abort_unless(! array_diff(array_keys($node), ['mode', 'rules']) && in_array($node['mode'] ?? null, ['and', 'or'], true) && is_array($node['rules']) && array_is_list($node['rules']) && count($node['rules']) >= 1 && count($node['rules']) <= 20, 422, 'Invalid view condition group.');
            foreach ($node['rules'] as $child) {
                abort_unless(is_array($child), 422);
                $this->validate($request, $project, $child, $depth + 1, $count);
            }

            return;
        }
        abort_unless(! array_diff(array_keys($node), ['field', 'operator', 'value']) && array_key_exists('value', $node), 422);
        $field = $node['field'] ?? '';
        $operator = $node['operator'] ?? '';
        $value = $node['value'];
        $enum = match ($field) {
            'priority' => ['critical', 'high', 'medium', 'low'],
            'status' => ['draft', 'pending', 'approved'],
            'type' => ['functional', 'regression', 'smoke', 'security', 'performance'],
            default => null,
        };
        if ($enum) {
            $values = $operator === 'in' ? $value : [$value];
            abort_unless(in_array($operator, ['eq', 'in'], true) && is_array($values) && array_is_list($values) && count($values) >= 1 && count($values) <= 10 && count(array_filter($values, 'is_string')) === count($values) && ! array_diff($values, $enum), 422, 'Invalid enum view condition.');

            return;
        }
        if (in_array($field, ['title', 'tag'], true)) {
            abort_unless(is_string($value) && mb_strlen($value) >= 1 && mb_strlen($value) <= 200 && in_array($operator, $field === 'title' ? ['eq', 'contains'] : ['eq'], true), 422);

            return;
        }
        if ($field === 'assignee_id') {
            abort_unless($operator === 'eq' && ($value === null || is_int($value)), 422);
            if ($value !== null) {
                abort_unless(DB::table('project_members')->where('project_id', $project)->where('user_id', $value)->exists(), 404);
            }

            return;
        }
        abort_unless(in_array($field, ['section_id', 'requirement_id'], true) && is_string($value) && Str::isUuid($value), 422, 'Unsupported view field.');
        if ($field === 'section_id') {
            abort_unless(in_array($operator, ['eq', 'descendants'], true), 422);
            app(Access::class)->check($request, $project, 'project.view', $value);
        } else {
            abort_unless($operator === 'eq', 422);
            app(DomainRecords::class)->find($request, $project, 'requirements', $value);
        }
    }

    public function query(Request $request, string $project, array $conditions): Builder
    {
        $this->validate($request, $project, $conditions);
        $query = app(Access::class)->scope(DB::table('test_cases')->where('test_cases.project_id', $project), $request, $project, 'test_cases.section_id');
        $this->apply($query, $conditions, $project);

        return $query;
    }

    private function apply(Builder $query, array $node, string $project): void
    {
        if (isset($node['rules'])) {
            $query->where(function (Builder $group) use ($node, $project) {
                foreach ($node['rules'] as $child) {
                    $method = $node['mode'] === 'or' ? 'orWhere' : 'where';
                    $group->{$method}(fn (Builder $part) => $this->apply($part, $child, $project));
                }
            });

            return;
        }
        $field = $node['field'];
        $value = $node['value'];
        $operator = $node['operator'];
        if ($field === 'requirement_id') {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('case_requirement_links as link')->whereColumn('link.test_case_id', 'test_cases.id')->whereColumn('link.case_version', 'test_cases.version')->where('link.requirement_id', $value));
        } elseif ($field === 'tag') {
            $query->whereJsonContains('test_cases.content->tags', $value);
        } elseif ($field === 'section_id' && $operator === 'descendants') {
            $nodes = DB::table('sections')->where('project_id', $project)->get();
            $ids = [$value];
            for ($depth = 0; $depth < 8; $depth++) {
                $next = $nodes->whereIn('parent_id', $ids)->pluck('id')->all();
                $merged = array_values(array_unique([...$ids, ...$next]));
                if (count($merged) === count($ids)) {
                    break;
                }
                $ids = $merged;
            }
            $query->whereIn('test_cases.section_id', $ids);
        } else {
            $column = $field === 'type' ? 'test_cases.content->type' : 'test_cases.'.$field;
            if ($operator === 'contains') {
                $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
                $query->where($column, 'ilike', '%'.$literal.'%');
            } elseif ($operator === 'in') {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }
    }
}
