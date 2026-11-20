<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\AssignmentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignmentController extends Controller
{
    public function update(Request $request, string $project, string $case): JsonResponse
    {
        $values = $request->validate(['assignee_id' => 'present|nullable|integer', 'substitute_id' => 'present|nullable|integer', 'reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($request, $project, $case, $values) {
            $record = DB::table('test_cases')->where('id', $case)->where('project_id', $project)->lockForUpdate()->first();
            abort_unless($record, 404);
            app(Access::class)->check($request, $project, 'cases.assign', $record->section_id);
            abort_unless((string) $record->assignment_version === $request->header('If-Match'), 409, 'The assignment changed. Reload before saving.');
            app(AssignmentRules::class)->validate($project, $values);
            $next = ['assignee_id' => $values['assignee_id'], 'substitute_id' => $values['substitute_id'], 'assignment_version' => $record->assignment_version + 1];
            DB::table('test_cases')->where('id', $case)->update($next + ['updated_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'section_id' => $record->section_id, 'actor_id' => $request->user()->id, 'action' => 'cases.assign', 'entity_id' => $case, 'details' => json_encode(['actor_name' => $request->user()->name, 'before' => ['assignee_id' => $record->assignee_id, 'substitute_id' => $record->substitute_id, 'assignment_version' => $record->assignment_version], 'after' => $next, 'reason' => $values['reason']]), 'created_at' => now(), 'updated_at' => now()]);
        });
        $result = DB::table('test_cases')->where('id', $case)->where('project_id', $project)->first();

        return response()->json(['data' => ['id' => $case, 'assignee_id' => $result->assignee_id, 'substitute_id' => $result->substitute_id, 'assignment_version' => $result->assignment_version, 'version' => $result->version, 'status' => $result->status]]);
    }
}
