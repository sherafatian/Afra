<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExecutionPlanning
{
    public function expand(Request $request, string $project, string $planId): array
    {
        app(Access::class)->check($request, $project, 'runs.create');
        $records = app(DomainRecords::class);
        $cache = [];
        $find = function (string $kind, string $id) use ($records, $request, $project, &$cache) {
            return $cache[$kind.':'.$id] ??= $records->find($request, $project, $kind, $id, true);
        };
        $plan = $find('plans', $planId);
        $definition = $records->validate($request, $project, 'plans', $plan->content, false);
        $sources = ['plan' => $this->reference($plan)];
        if (! empty($definition['saved_view_id'])) {
            $view = $find('saved-views', $definition['saved_view_id']);
            $records->validate($request, $project, 'saved-views', $view->content, false);
            $query = app(CaseViews::class)->query($request, $project, $view->content['conditions']);
            $sources['view'] = $this->reference($view);
        } else {
            $query = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project)->whereIn('id', $definition['case_ids']), $request, $project);
        }
        $cases = $query->select('test_cases.id')->orderBy('id')->limit(1001)->lockForUpdate()->get();
        abort_if($cases->isEmpty() || $cases->count() > 1000, 422, 'Plan scope must contain1–1000 visible cases.');
        $configurations = [['id' => 'default', 'name' => 'Default', 'required' => true]];
        if (! empty($definition['configuration_profile_id'])) {
            $profile = $find('configuration-profiles', $definition['configuration_profile_id']);
            $value = $records->validate($request, $project, 'configuration-profiles', $profile->content);
            $sources['profile'] = $this->reference($profile);
            $configurations = $value['rows'];
        }
        if (! empty($definition['release_id'])) {
            $release = $find('releases', $definition['release_id']);
            $sources['release'] = $this->reference($release);
        }
        $dateFormatter = null;
        $items = [];
        $bytes = 0;
        foreach ($cases as $identity) {
            $case = DB::table('test_cases')->where('id', $identity->id)->first();
            app(Access::class)->check($request, $project, 'runs.create', $case->section_id);
            abort_unless($case->status === 'approved', 409, 'All planned cases require their current independently approved revision.');
            $bindings = app(RequirementCoverage::class)->bindings($case);
            $content = json_decode($case->content, true);
            foreach ($configurations as $configuration) {
                $frozenConfiguration = $configuration;
                foreach (['environment_id' => 'environments', 'build_id' => 'builds'] as $field => $kind) {
                    if (! empty($configuration[$field])) {
                        $source = $find($kind, $configuration[$field]);
                        if ($kind === 'builds') {
                            abort_unless(($source->content['environment_id'] ?? null) === ($configuration['environment_id'] ?? null), 422, 'Build/environment mismatch.');
                            abort_if(! empty($definition['release_id']) && ! empty($source->content['release_id']) && $definition['release_id'] !== $source->content['release_id'], 422, 'Build/release mismatch.');
                        }
                        $frozenConfiguration[$kind] = $this->reference($source);
                    }
                }
                $rows = [[]];
                $datasetReference = null;
                if (! empty($configuration['dataset_id'])) {
                    $dataset = $find('datasets', $configuration['dataset_id']);
                    $valid = $records->validate($request, $project, 'datasets', $dataset->content);
                    $rows = $valid['rows'];
                    $datasetReference = ['id' => $dataset->id, 'version' => $dataset->version, 'title' => $dataset->title, 'columns' => $valid['columns']];
                }
                foreach ($rows as $rowIndex => $values) {
                    $variables = array_replace($content['variables'] ?? [], $values);
                    foreach ($datasetReference['columns'] ?? [] as $column) {
                        $date = $values[$column['key']] ?? null;
                        if ($column['type'] === 'date' && is_string($date) && $date !== '') {
                            $dateFormatter ??= new \IntlDateFormatter('en_US@calendar=persian', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Tehran', \IntlDateFormatter::TRADITIONAL, 'yyyy/MM/dd');
                            $variables[$column['key']] = $dateFormatter->format(CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Tehran')->getTimestamp());
                        }
                    }
                    $snapshot = $this->snapshot($case, $bindings, $variables);
                    $key = hash('sha256', json_encode([$case->id, $case->version, $configuration['id'], $rowIndex]));
                    $bytes += strlen(json_encode([$snapshot, $frozenConfiguration, $values]));
                    abort_if($bytes > 8 * 1024 * 1024, 422, 'Expanded snapshot exceeds8MiB. Reduce this execution scope.');
                    $items[] = ['key' => $key, 'case_id' => $case->id, 'section_id' => $case->section_id, 'required' => $configuration['required'], 'configuration' => $frozenConfiguration, 'data' => ['dataset' => $datasetReference, 'row' => $rowIndex, 'values' => $values], 'snapshot' => $snapshot];
                    abort_if(count($items) > 1000, 422, 'Expansion exceeds1000 execution items. Reduce cases/configurations/data rows.');
                }
            }
        }

        return ['plan_id' => $plan->id, 'release_id' => $definition['release_id'] ?? null, 'sources' => $sources, 'unique_cases' => $cases->count(), 'items' => $items, 'acl_version' => $request->user()->acl_version];
    }

    public function snapshot(object $case, array $bindings, ?array $variables = null): array
    {
        $content = json_decode($case->content, true);
        abort_unless(is_array($content['checklist'] ?? null) && array_is_list($content['checklist']) && count($content['checklist']) > 0, 422, 'Checklist requires a corrected case revision before execution.');
        $variables ??= $content['variables'] ?? [];

        return ['case_id' => $case->id, 'version' => $case->version, 'title' => $this->substitute($case->title, $variables, 250), 'assignment_version' => $case->assignment_version, 'assignee_id' => $case->assignee_id, 'substitute_id' => $case->substitute_id, 'requirements' => $bindings, 'content' => $this->materialize($content, $variables), 'source_content' => $content];
    }

    private function reference(object $record): array
    {
        return ['id' => $record->id, 'version' => $record->version, 'title' => $record->title, 'content' => $record->content];
    }

    private function materialize(array $content, array $variables): array
    {
        foreach (['preconditions', 'expected'] as $key) {
            if (isset($content[$key])) {
                $content[$key] = $this->substitute($content[$key], $variables, 10000);
            }
        }
        foreach ($content['checklist'] as &$step) {
            $step['text'] = $this->substitute($step['text'], $variables);
            $step['expected'] = $this->substitute($step['expected'], $variables);
        }

        return $content;
    }

    private function substitute(string $text, array $variables, int $maximum = 3000): string
    {
        $rendered = preg_replace_callback('/\{\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}/', function ($match) use ($variables) {
            abort_unless(array_key_exists($match[1], $variables) && is_scalar($variables[$match[1]]), 422, 'Unresolved or invalid variable: '.$match[1]);

            return is_bool($variables[$match[1]]) ? ($variables[$match[1]] ? 'true' : 'false') : (string) $variables[$match[1]];
        }, $text);
        abort_if(mb_strlen($rendered) > $maximum, 422, 'Expanded text exceeds its allowed length. Reduce variable values.');
        abort_if(preg_match('/\{\{[^{}]*\}\}/', $rendered), 422, 'Invalid or unresolved variable expression.');

        return $rendered;
    }
}
