<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DomainRecords
{
    public const KINDS = ['requirements', 'plans', 'releases', 'environments', 'builds', 'templates', 'datasets', 'shared-steps', 'saved-views', 'custom-fields', 'configuration-profiles'];

    public function find(Request $request, string $project, string $kind, string $id, bool $lock = false, bool $allowArchived = false): object
    {
        $query = DB::table('project_records')->where('id', $id)->where('project_id', $project)->where('kind', $kind);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);
        $record->content = json_decode($record->content, true);
        if ($kind === 'saved-views') {
            abort_unless(($record->content['visibility'] ?? 'team') === 'team' || $record->owner_id === $request->user()->id, 404);
        }
        abort_if($record->archived && ! $allowArchived, 409, 'Referenced record is archived.');

        return $record;
    }

    public function validate(Request $request, string $project, string $kind, array $content, bool $writing = true): array
    {
        abort_if(strlen(json_encode($content)) > 2 * 1024 * 1024, 422, 'Definition exceeds2MiB. Split the definition into smaller datasets or records.');
        $rules = match ($kind) {
            'plans' => ['case_ids' => 'present|array|list|max:1000', 'case_ids.*' => 'uuid|distinct', 'saved_view_id' => 'nullable|uuid', 'configuration_profile_id' => 'nullable|uuid', 'release_id' => 'nullable|uuid', 'description' => 'nullable|string|max:10000'],
            'configuration-profiles' => ['rows' => 'required|array|list|min:1|max:20', 'rows.*' => 'array:id,name,environment_id,build_id,browser,os,device,dataset_id,required', 'rows.*.id' => 'required|uuid|distinct', 'rows.*.name' => 'required|string|max:100', 'rows.*.environment_id' => 'nullable|uuid', 'rows.*.build_id' => 'nullable|uuid', 'rows.*.dataset_id' => 'nullable|uuid', 'rows.*.browser' => 'nullable|string|max:100', 'rows.*.os' => 'nullable|string|max:100', 'rows.*.device' => 'nullable|string|max:100', 'rows.*.required' => 'required|boolean'],
            'datasets' => ['columns' => 'required|array|list|min:1|max:30', 'columns.*' => 'array:key,type,required', 'columns.*.key' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{0,49}$/', 'distinct'], 'columns.*.type' => 'required|in:string,number,boolean,date', 'columns.*.required' => 'required|boolean', 'rows' => 'required|array|list|min:1|max:500', 'rows.*' => 'array'],
            'saved-views' => ['visibility' => 'required|in:personal,team', 'conditions' => 'required|array', 'order' => 'sometimes|in:title,priority,updated_at', 'direction' => 'sometimes|in:asc,desc', 'columns' => 'sometimes|array|list|min:1|max:8', 'columns.*' => 'in:title,priority,status,assignee_id,section_id,version,updated_at'],
            'templates', 'shared-steps' => ['preconditions' => 'nullable|string|max:10000', 'expected' => 'nullable|string|max:10000', 'type' => 'nullable|in:functional,regression,smoke,security,performance', 'checklist' => 'required|array|list|min:1|max:200', 'checklist.*' => 'array:text,expected,required', 'checklist.*.text' => 'required|string|max:3000', 'checklist.*.expected' => 'required|string|max:3000', 'checklist.*.required' => 'required|boolean'],
            'environments' => ['description' => 'nullable|string|max:10000', 'url' => 'nullable|url:http,https|max:1000'],
            'builds' => ['description' => 'nullable|string|max:10000', 'environment_id' => 'required|uuid', 'release_id' => 'nullable|uuid', 'label' => 'required|string|max:150'],
            'releases' => ['description' => 'nullable|string|max:10000', 'product_version' => 'required|string|max:100', 'gates' => 'required|array:max_open_critical,minimum_pass_rate,allow_blocked,allow_forced_critical', 'gates.max_open_critical' => 'required|integer|min:0|max:1000', 'gates.minimum_pass_rate' => 'required|numeric|min:0|max:100', 'gates.allow_blocked' => 'required|boolean', 'gates.allow_forced_critical' => 'sometimes|boolean'],
            default => null,
        };
        if ($rules === null) {
            return $content;
        }
        $allowed = array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.'));
        abort_if(array_diff(array_keys($content), $allowed), 422, 'Unsupported definition fields.');
        $value = Validator::make($content, $rules)->validate();
        if ($kind === 'configuration-profiles') {
            foreach ($value['rows'] as &$row) {
                $row['required'] = (bool) $row['required'];
            } unset($row);
        }
        if ($kind === 'datasets') {
            foreach ($value['columns'] as &$column) {
                $column['required'] = (bool) $column['required'];
            } unset($column);
            foreach ($value['rows'] as $index => $row) {
                $this->validateValues($value['columns'], $row, 'rows.'.$index);
            }
        }
        if ($kind === 'plans') {
            abort_if((bool) count($value['case_ids']) === ! empty($value['saved_view_id']), 422, 'Choose explicit cases or a saved view, exclusively.');
            $cases = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project)->whereIn('id', $value['case_ids']), $request, $project)->count();
            abort_unless($cases === count($value['case_ids']), 404);
            foreach (['saved_view_id' => 'saved-views', 'configuration_profile_id' => 'configuration-profiles', 'release_id' => 'releases'] as $key => $target) {
                if (! empty($value[$key])) {
                    $this->find($request, $project, $target, $value[$key]);
                }
            }
        }
        if ($kind === 'configuration-profiles') {
            foreach ($value['rows'] as $row) {
                foreach (['environment_id' => 'environments', 'build_id' => 'builds', 'dataset_id' => 'datasets'] as $key => $target) {
                    if (! empty($row[$key])) {
                        $record = $this->find($request, $project, $target, $row[$key]);
                        if ($target === 'builds') {
                            abort_unless(($record->content['environment_id'] ?? null) === ($row['environment_id'] ?? null), 422, 'Build does not belong to this environment.');
                        }
                    }
                }
            }
        }
        if ($kind === 'environments' && ! empty($value['url'])) {
            $url = parse_url($value['url']);
            abort_if(isset($url['user']) || isset($url['pass']), 422, 'Environment URLs must not contain credentials.');
        }
        if ($kind === 'builds') {
            $this->find($request, $project, 'environments', $value['environment_id']);
            if (! empty($value['release_id'])) {
                $this->find($request, $project, 'releases', $value['release_id']);
            }
        }
        if ($kind === 'saved-views') {
            $value['columns'] = array_values(array_unique(['title', ...($value['columns'] ?? ['priority', 'status', 'version'])]));
            app(CaseViews::class)->validate($request, $project, $value['conditions']);
            if ($writing && $value['visibility'] === 'team') {
                app(Access::class)->check($request, $project, 'views.manage');
            }
        }

        return $value;
    }

    public function validateValues(array $columns, array $row, string $path = 'values'): void
    {
        if (array_diff(array_keys($row), array_column($columns, 'key'))) {
            throw ValidationException::withMessages([$path => 'Unknown data column.']);
        }
        foreach ($columns as $column) {
            $value = $row[$column['key']] ?? null;
            if ($value === null || $value === '') {
                if ($column['required']) {
                    throw ValidationException::withMessages([$path.'.'.$column['key'] => 'Required value is missing.']);
                }

                continue;
            }
            $valid = match ($column['type']) {
                'string' => is_string($value) && mb_strlen($value) <= 3000,
                'number' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                'boolean' => is_bool($value),
                'date' => is_string($value) && ! Validator::make(['date' => $value], ['date' => 'date_format:Y-m-d'])->fails(),
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages([$path.'.'.$column['key'] => 'Value does not match the declared type.']);
            }
        }
    }
}
