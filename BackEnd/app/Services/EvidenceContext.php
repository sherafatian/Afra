<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvidenceContext
{
    public function resolve(Request $request, string $project, string $type, string $id, string $action): ?string
    {
        if ($type === 'project') {
            abort_unless($id === $project, 404);
            app(Access::class)->check($request, $project, $action);

            return null;
        }
        if ($type === 'case') {
            $row = DB::table('test_cases')->where('project_id', $project)->where('id', $id)->first();
        } else {
            $query = DB::table('run_items')->join('test_runs', 'test_runs.id', '=', 'run_items.test_run_id')->where('test_runs.project_id', $project)->select('run_items.section_id');
            if ($type === 'run_item') {
                $query->where('run_items.id', $id);
            } elseif ($type === 'result') {
                $query->join('test_results', 'test_results.run_item_id', '=', 'run_items.id')->where('test_results.id', $id);
            } elseif ($type === 'defect') {
                $query->join('defects', 'defects.run_item_id', '=', 'run_items.id')->where('defects.id', $id);
            } else {
                abort(422, 'Invalid evidence context.');
            }
            $row = $query->first();
        }
        abort_unless($row, 404);
        app(Access::class)->check($request,$project,$action,$row->section_id);

        return $row->section_id;
    }
}
