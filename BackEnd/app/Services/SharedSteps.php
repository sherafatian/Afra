<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SharedSteps
{
    /** Resolve only independently approved immutable revisions; never follow a mutable current version. */
    public function compose(Request $request, string $project, array $content, array $previous = []): array
    {
        $blocks = $content['shared_blocks'] ?? [];
        $local = $content['checklist'];
        $sources = [];
        $sorted = $blocks;
        usort($sorted, fn ($a, $b) => strcmp($a['id'], $b['id']));
        foreach ($sorted as $block) {
            $prior = collect($previous['shared_blocks'] ?? [])->firstWhere('id', $block['id']);
            if ($prior && $prior['version'] !== $block['version']) {
                app(Access::class)->check($request, $project, 'shared.update_cases');
            }
            $record = app(DomainRecords::class)->find($request, $project, 'shared-steps', $block['id'], true, $prior && $prior['version'] === $block['version']);
            $revision = DB::table('project_record_revisions')->where('record_id', $record->id)->where('version', $block['version'])->first();
            abort_unless($revision && $revision->review_status === 'approved', 409, 'Shared steps require an independently approved revision.');
            $snapshot = json_decode($revision->snapshot, true);
            $sources[$block['id']] = ['id' => $record->id, 'version' => $block['version'], 'title' => $snapshot['title'], 'checklist' => $snapshot['content']['checklist']];
        }
        if ($blocks) {
            $content['local_checklist'] = $local;
            $content['shared_sources'] = [];
            $resolved = [];
            for ($position = 0; $position <= count($local); $position++) {
                foreach ($blocks as $block) {
                    if ($block['position'] === $position) {
                        $source = $sources[$block['id']];
                        $content['shared_sources'][] = $source;
                        array_push($resolved, ...$source['checklist']);
                    }
                }
                if (isset($local[$position])) {
                    $resolved[] = $local[$position];
                }
            }
            $content['checklist'] = $resolved;
        }
        abort_if(count($content['checklist']) > 200 || strlen(json_encode($content)) > 131072, 422, 'Combined shared/local steps exceed the case size limit.');

        return $content;
    }
}
