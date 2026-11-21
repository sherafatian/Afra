<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutionSummary
{
    public function items(Request $request, string $project, array $runs): Collection
    {
        $query = app(Access::class)->scope(DB::table('run_items')->whereIn('test_run_id', $runs), $request, $project);
        abort_if((clone $query)->count() > 10000, 422, 'Report exceeds10000 items. Narrow the report scope.');

        return $query->orderBy('id')->get(['id', 'test_case_id', 'baseline_item_id', 'required', DB::raw("json_build_object('title', snapshot->>'title', 'version', (snapshot->>'version')::integer) AS snapshot"), DB::raw("json_build_object('name', configuration->>'name') AS configuration"), DB::raw("json_build_object('row', data_snapshot->'row', 'dataset', json_build_object('title', data_snapshot->'dataset'->>'title')) AS data_snapshot")]);
    }

    public function calculate(Request $request, string $project, Collection $items): array
    {
        $roots = $items->map(fn ($item) => $item->baseline_item_id ?? $item->id)->unique()->all();
        $runs = DB::table('test_runs')->where('project_id', $project)->select('id');
        $visible = app(Access::class)->scope(DB::table('run_items')->whereIn('test_run_id', $runs), $request, $project)
            ->where(fn ($query) => $query->whereIn('id', $roots)->orWhereIn('baseline_item_id', $roots))->select('id', 'baseline_item_id');
        $latest = DB::table('test_results as result')->joinSub($visible, 'item', 'item.id', '=', 'result.run_item_id')
            ->selectRaw('COALESCE(item.baseline_item_id, item.id) AS root_id, MAX(result.recorded_order) AS latest')->groupByRaw('COALESCE(item.baseline_item_id, item.id)');
        $observations = DB::table('test_results as result')->joinSub($latest, 'observed', 'observed.latest', '=', 'result.recorded_order')->select('result.*', 'observed.root_id')->get()->keyBy('root_id');
        $rows = $items->map(function ($item) use ($observations) {
            $root = $item->baseline_item_id ?? $item->id;
            $result = $observations->get($root);
            $snapshot = is_string($item->snapshot) ? json_decode($item->snapshot, true) : $item->snapshot;

            return ['id' => $item->id, 'case_id' => $item->test_case_id, 'case_revision' => $snapshot['version'], 'title' => $snapshot['title'], 'required' => (bool) $item->required, 'configuration' => is_string($item->configuration) ? json_decode($item->configuration, true) : $item->configuration, 'data' => is_string($item->data_snapshot) ? json_decode($item->data_snapshot, true) : $item->data_snapshot, 'outcome' => $result?->outcome ?? 'not_run', 'result_id' => $result?->id, 'recorded_at' => $result?->created_at, 'actor_name' => $result?->actor_name, 'is_retest' => $result && $result->run_item_id !== $item->id];
        });
        $required = $rows->where('required', true);
        $counts = $rows->countBy('outcome');
        $requiredCounts = $required->countBy('outcome');
        $cases = $rows->groupBy('case_id')->map(function ($variants, $id) {
            $requiredVariants = $variants->where('required', true);
            $outcome = $requiredVariants->isEmpty() ? 'optional' : ($requiredVariants->every(fn ($item) => $item['outcome'] === 'pass') ? 'pass' : ($requiredVariants->contains('outcome', 'fail') ? 'fail' : ($requiredVariants->contains('outcome', 'blocked') ? 'blocked' : 'not_run')));

            return ['case_id' => $id, 'title' => $variants->first()['title'], 'variants' => $variants->count(), 'required_variants' => $requiredVariants->count(), 'outcome' => $outcome];
        })->values();

        return ['total_items' => $rows->count(), 'unique_cases' => $cases->count(), 'required_items' => $required->count(), 'optional_items' => $rows->count() - $required->count(), 'counts' => $counts, 'required_counts' => $requiredCounts, 'required_pass_rate' => $required->isEmpty() ? 0 : round(100 * ($requiredCounts['pass'] ?? 0) / $required->count(), 2), 'items' => $rows->values(), 'cases' => $cases];
    }
}
