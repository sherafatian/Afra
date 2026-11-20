<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequirementCoverage
{
    /** Validate and record links for one immutable case revision. */
    public function link(Request $request, string $project, string $case, int $version, array $links): void
    {
        foreach (collect($links)->sortBy('id') as $link) {
            $record = DB::table('project_records')->where('id', $link['id'])->where('project_id', $project)->where('kind', 'requirements')->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->archived || $record->version !== (int) $link['version'], 409, 'Requirement changed or is archived. Refresh before linking.');
            DB::table('case_requirement_links')->insert(['test_case_id' => $case, 'case_version' => $version, 'requirement_id' => $record->id, 'requirement_version' => $record->version]);
        }
    }

    /** Resolve current compatible versions, with locks inside review/run transactions. */
    public function bindings(object $case, bool $strict = true): array
    {
        $links = DB::table('case_requirement_links')->where('test_case_id', $case->id)->where('case_version', $case->version)->orderBy('requirement_id')->get();
        $bindings = [];
        foreach ($links as $link) {
            $requirement = DB::table('project_records')->where('id', $link->requirement_id)->lockForUpdate()->first();
            $compatible = ! $requirement->archived && ($requirement->version === $link->requirement_version || DB::table('requirement_compatibilities')->where('test_case_id', $case->id)->where('case_version', $case->version)->where('requirement_id', $requirement->id)->where('requirement_version', $requirement->version)->exists());
            abort_if($strict && ! $compatible, 409, 'Requirement coverage is stale. Revise the case or obtain independent compatibility confirmation.');
            $bindings[] = ['id' => $requirement->id, 'version' => $requirement->version, 'linked_version' => $link->requirement_version, 'compatible' => $compatible, 'title' => $requirement->title];
        }

        return $bindings;
    }
}
