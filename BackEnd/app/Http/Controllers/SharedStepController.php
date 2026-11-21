<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\DomainRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedStepController extends Controller
{
    public function revision(Request $request, string $project, string $record, int $version): JsonResponse
    {
        app(Access::class)->check($request, $project);
        app(DomainRecords::class)->find($request, $project, 'shared-steps', $record, false, true);
        $row = DB::table('project_record_revisions')->where('record_id', $record)->where('version', $version)->first();
        abort_unless($row, 404);
        $row->snapshot = json_decode($row->snapshot, true);

        return response()->json(['data' => $row]);
    }

    public function review(Request $request, string $project, string $record): JsonResponse
    {
        $v = $request->validate(['version' => 'required|integer|min:1', 'decision' => 'required|in:submit,approve,reject', 'reason' => 'required_if:decision,reject|nullable|string|min:10|max:2000']);
        app(Access::class)->check($request, $project, $v['decision'] === 'submit' ? 'shared.request_review' : 'shared.approve');
        DB::transaction(function () use ($request, $project, $record, $v) {
            $current = app(DomainRecords::class)->find($request, $project, 'shared-steps', $record, true);
            abort_unless($current->version === $v['version'], 409, 'Shared steps changed. Review the current revision.');
            $revision = DB::table('project_record_revisions')->where('record_id', $record)->where('version', $v['version'])->lockForUpdate()->first();
            abort_unless($revision, 404);
            abort_unless($revision->author_id, 409, 'Save a new attributed revision before requesting review.');
            if ($v['decision'] === 'submit') {
                abort_unless($revision->review_status === 'draft', 409);
                $status = 'pending';
            } else {
                abort_unless($revision->review_status === 'pending', 409);
                abort_if($revision->author_id === $request->user()->id, 403, 'An independent reviewer must decide.');
                $status = $v['decision'] === 'approve' ? 'approved' : 'draft';
            }
            DB::table('project_record_revisions')->where('record_id', $record)->where('version', $v['version'])->update(['review_status' => $status, 'reviewer_id' => $v['decision'] === 'submit' ? null : $request->user()->id, 'review_reason' => $v['reason'] ?? null, 'reviewed_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'actor_id' => $request->user()->id, 'action' => 'shared.'.$v['decision'], 'entity_id' => $record, 'details' => json_encode($v), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => true]);
    }

    public function consumers(Request $request, string $project, string $record): JsonResponse
    {
        app(Access::class)->check($request, $project);
        app(DomainRecords::class)->find($request, $project, 'shared-steps', $record, false, true);
        $query = app(Access::class)->scope(DB::table('test_cases')->where('project_id', $project), $request, $project);

        return response()->json(['data' => $query->whereJsonContains('content->shared_blocks', ['id' => $record])->orderBy('id')->paginate(25, ['id', 'title', 'version', 'status', 'content->shared_blocks as shared_blocks'])]);
    }
}
