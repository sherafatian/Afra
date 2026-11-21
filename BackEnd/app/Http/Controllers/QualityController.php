<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\CaseViews;
use App\Services\DomainRecords;
use App\Services\ExecutionSummary;
use App\Services\RequirementCoverage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QualityController extends Controller
{
    public function execution(Request $request, string $project, string $run): JsonResponse
    {
        app(Access::class)->check($request, $project, 'reports.read');
        if ($request->boolean('export')) {
            app(Access::class)->check($request, $project, 'reports.export');
        }
        $record = DB::table('test_runs')->where('id', $run)->where('project_id', $project)->first();
        abort_unless($record, 404);
        $items = app(ExecutionSummary::class)->items($request, $project, [$run]);
        abort_if($items->isEmpty(), 404);
        $data = app(ExecutionSummary::class)->calculate($request, $project, $items);

        return response()->json(['data' => ['id' => $run, 'title' => $record->title, 'created_at' => $record->created_at, 'prepared_at' => now(), 'scope' => 'visible_execution_baseline_with_latest_linked_retests', 'summary' => $data]]);
    }

    public function release(Request $request, string $project, string $record): JsonResponse
    {
        app(Access::class)->check($request, $project, 'reports.read');
        if ($request->boolean('export')) {
            app(Access::class)->check($request, $project, 'reports.export');
        }
        $release = app(DomainRecords::class)->find($request, $project, 'releases', $record);
        $content = app(DomainRecords::class)->validate($request, $project, 'releases', $release->content, false);
        $plans = DB::table('project_records')->where('project_id', $project)->where('kind', 'plans')->where('archived', false)->where('content->release_id', $record)->get();
        $baselines = DB::table('test_runs')->where('project_id', $project)->where('release_id', $record)->whereIn('plan_id', $plans->pluck('id'))->selectRaw('DISTINCT ON (plan_id) id, plan_id, scope_snapshot, run_order')->orderBy('plan_id')->orderByDesc('run_order')->get();
        $items = app(ExecutionSummary::class)->items($request, $project, $baselines->pluck('id')->all());
        $summary = app(ExecutionSummary::class)->calculate($request, $project, $items);
        $allRuns = DB::table('test_runs')->where('project_id', $project)->select('id');
        $releaseRoots = DB::table('run_items')->whereIn('test_run_id', DB::table('test_runs')->where('project_id', $project)->where('release_id', $record)->select('id'))->select('id');
        $allRelease = DB::table('run_items')->whereIn('test_run_id', $allRuns)->where(fn ($q) => $q->whereIn('id', clone $releaseRoots)->orWhereIn('baseline_item_id', clone $releaseRoots));
        $visibleRelease = app(Access::class)->scope(clone $allRelease, $request, $project);
        $completeScope = (clone $allRelease)->count() === (clone $visibleRelease)->count();
        $critical = DB::table('defects')->where('project_id', $project)->where('severity', 'critical')->where('status', '!=', 'closed')->whereIn('run_item_id', (clone $visibleRelease)->select('id'))->count();
        $fresh = $plans->count() > 0 && $plans->count() === $baselines->count();
        foreach ($baselines as $baseline) {
            $scope = json_decode($baseline->scope_snapshot ?? '{}', true);
            $fresh = $fresh && ($scope['sources']['release']['version'] ?? null) === $release->version && $this->fresh($request, $project, $plans->firstWhere('id', $baseline->plan_id), $scope);
        }
        $forcedCritical = DB::table('defects')->where('project_id', $project)->where('severity', 'critical')->where('closure_type', 'forced')->whereIn('run_item_id', (clone $visibleRelease)->select('id'))->count();
        $gates = $content['gates'];
        $checks = ['complete_scope' => $completeScope, 'fresh_scope' => $fresh, 'forced_critical' => ($gates['allow_forced_critical'] ?? false) || $forcedCritical === 0, 'has_required_items' => $summary['required_items'] > 0, 'required_execution_complete' => ($summary['required_counts']['not_run'] ?? 0) === 0 && ($summary['required_counts']['skipped'] ?? 0) === 0, 'pass_rate' => $summary['required_pass_rate'] >= $gates['minimum_pass_rate'], 'open_critical' => $critical <= $gates['max_open_critical'], 'blocked' => $gates['allow_blocked'] || ($summary['required_counts']['blocked'] ?? 0) === 0];

        return response()->json(['data' => ['release_id' => $record, 'release_version' => $release->version, 'title' => $release->title, 'prepared_at' => now(), 'summary' => $summary, 'open_critical' => $critical, 'forced_critical' => $forcedCritical, 'gates' => $gates, 'checks' => $checks, 'ready' => ! in_array(false, $checks, true), 'scope' => 'latest_plan_baselines_visible_to_current_user']]);
    }

    private function fresh(Request $request, string $project, object $plan, array $scope): bool
    {
        if (($scope['sources']['plan']['version'] ?? 0) !== $plan->version) {
            return false;
        }
        $definition = json_decode($plan->content, true);
        try {
            foreach (['view' => 'saved-views', 'profile' => 'configuration-profiles'] as $key => $kind) {
                if (isset($scope['sources'][$key])) {
                    $current = app(DomainRecords::class)->find($request, $project, $kind, $scope['sources'][$key]['id']);
                    if ($current->version !== $scope['sources'][$key]['version']) {
                        return false;
                    }
                }
            }
            if (! empty($definition['saved_view_id'])) {
                $view = app(DomainRecords::class)->find($request, $project, 'saved-views', $definition['saved_view_id']);
                $cases = app(CaseViews::class)->query($request, $project, $view->content['conditions'])->orderBy('id')->limit(1001)->get(['id', 'version', 'status', 'section_id']);
            } else {
                $cases = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project)->whereIn('id', $definition['case_ids']), $request, $project)->orderBy('id')->get(['id', 'version', 'status', 'section_id']);
            }
            $identities = $cases->map(fn ($case) => ['id' => $case->id, 'version' => $case->version])->all();
            if ($identities !== ($scope['source_cases'] ?? [])) {
                return false;
            }
            foreach ($cases as $case) {
                if ($case->status !== 'approved') {
                    return false;
                }
                $bindings = app(RequirementCoverage::class)->bindings($case, false);
                foreach ($bindings as $binding) {
                    if (! $binding['compatible']) {
                        return false;
                    }
                }
            }
            // Compare every frozen requirement binding against the currently referenced revision.
            foreach ($scope['requirement_versions'] ?? [] as $id => $version) {
                if ((int) DB::table('project_records')->where('id', $id)->where('project_id', $project)->value('version') !== $version) {
                    return false;
                }
            }
            foreach ($scope['dependency_versions'] ?? [] as $id => $version) {
                $current = DB::table('project_records')->where('id', $id)->where('project_id', $project)->first();
                if (! $current || $current->archived || $current->version !== $version) {
                    return false;
                }
            }

            return true;
        } catch (HttpException) {
            return false;
        }
    }
}
