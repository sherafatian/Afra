<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\ExecutionPlanning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanningController extends Controller
{
    public function preview(Request $request, string $project): JsonResponse
    {
        $v = $request->validate(['plan_id' => 'required|uuid']);
        $token = bin2hex(random_bytes(32));
        $scope = DB::transaction(function () use ($request, $project, $v, $token) {
            $scope = app(ExecutionPlanning::class)->expand($request, $project, $v['plan_id']);
            DB::table('execution_previews')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'user_id' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'source' => json_encode($v), 'fingerprint' => hash('sha256', json_encode($scope)), 'expires_at' => now()->addMinutes(5)]);

            return $scope;
        });

        return response()->json(['data' => ['preview_token' => $token, 'expires_in' => 300, 'unique_cases' => $scope['unique_cases'], 'total_items' => count($scope['items']), 'required_items' => count(array_filter($scope['items'], fn ($item) => $item['required'])), 'items' => $scope['items']]]);
    }

    public function create(Request $request, string $project): JsonResponse
    {
        app(Access::class)->check($request, $project, 'runs.create');
        $v = $request->validate(['preview_token' => 'required|string|size:64|regex:/^[0-9a-f]+$/', 'title' => 'required|string|max:250', 'selected' => 'sometimes|array|list|min:1|max:1000', 'selected.*' => 'required|string|size:64|distinct|regex:/^[0-9a-f]+$/']);
        $selected = $v['selected'] ?? null;
        if ($selected !== null) {
            sort($selected);
        }
        $confirmation = hash('sha256', json_encode(['title' => $v['title'], 'selected' => $selected]));
        $id = DB::transaction(function () use ($request, $project, $v, $selected, $confirmation) {
            $preview = DB::table('execution_previews')->where('project_id', $project)->where('user_id', $request->user()->id)->where('token_hash', hash('sha256', $v['preview_token']))->lockForUpdate()->first();
            abort_unless($preview, 404);
            if ($preview->consumed_at) {
                abort_unless(hash_equals($preview->confirmation_hash, $confirmation), 409, 'Preview was already used with different confirmation data.');
                foreach (DB::table('run_items')->where('test_run_id', $preview->run_id)->get(['section_id']) as $item) {
                    app(Access::class)->check($request, $project, 'runs.create', $item->section_id);
                }

                return $preview->run_id;
            }
            abort_if(now()->gte($preview->expires_at), 410, 'Preview expired. Generate a new preview.');
            $source = json_decode($preview->source, true);
            $scope = app(ExecutionPlanning::class)->expand($request, $project, $source['plan_id']);
            abort_unless(hash_equals($preview->fingerprint, hash('sha256', json_encode($scope))), 409, 'Scope changed after preview. Refresh before creating execution.');
            $keys = array_column($scope['items'], 'key');
            $chosen = $selected ?? $keys;
            abort_if(array_diff($chosen, $keys), 422, 'Unknown preview item.');
            $items = array_values(array_filter($scope['items'], fn ($item) => in_array($item['key'], $chosen, true)));
            foreach ($scope['items'] as $item) {
                abort_if($item['required'] && ! in_array($item['key'], $chosen, true), 422, 'Required configuration items cannot be deselected.');
            }
            $id = (string) Str::uuid();
            $metadata = ['sources' => $scope['sources'], 'source_cases' => collect($scope['items'])->map(fn ($item) => ['id' => $item['case_id'], 'version' => $item['snapshot']['version']])->unique('id')->sortBy('id')->values()->all(), 'unique_cases' => count(array_unique(array_column($items, 'case_id'))), 'total_items' => count($items), 'required_items' => count(array_filter($items, fn ($item) => $item['required']))];
            $metadata['requirement_versions'] = [];
            $metadata['dependency_versions'] = [];
            foreach ($scope['items'] as $item) {
                foreach ($item['snapshot']['requirements'] as $binding) {
                    $metadata['requirement_versions'][$binding['id']] = $binding['version'];
                }
                foreach (['environments', 'builds'] as $kind) {
                    if (isset($item['configuration'][$kind])) {
                        $reference = $item['configuration'][$kind];
                        $metadata['dependency_versions'][$reference['id']] = $reference['version'];
                    }
                }
                if ($item['data']['dataset']) {
                    $reference = $item['data']['dataset'];
                    $metadata['dependency_versions'][$reference['id']] = $reference['version'];
                }
            }
            DB::table('test_runs')->insert(['id' => $id, 'project_id' => $project, 'title' => $v['title'], 'created_by' => $request->user()->id, 'plan_id' => $scope['plan_id'], 'release_id' => $scope['release_id'], 'scope_snapshot' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as $item) {
                DB::table('run_items')->insert(['id' => (string) Str::uuid(), 'test_run_id' => $id, 'test_case_id' => $item['case_id'], 'section_id' => $item['section_id'], 'variant_key' => $item['key'], 'required' => $item['required'], 'configuration' => json_encode($item['configuration']), 'data_snapshot' => json_encode($item['data']), 'snapshot' => json_encode($item['snapshot']), 'requirement_bindings' => json_encode($item['snapshot']['requirements']), 'assignee_id' => $item['snapshot']['assignee_id'], 'substitute_id' => $item['snapshot']['substitute_id'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('execution_previews')->where('id', $preview->id)->update(['consumed_at' => now(), 'run_id' => $id, 'confirmation_hash' => $confirmation]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'actor_id' => $request->user()->id, 'action' => 'runs.create_from_plan', 'entity_id' => $id, 'details' => json_encode(['plan_id' => $scope['plan_id'], 'unique_cases' => $metadata['unique_cases'], 'total_items' => $metadata['total_items']]), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });

        return response()->json(['data' => DB::table('test_runs')->where('id', $id)->first()], 201);
    }
}
