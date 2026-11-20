<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\EvidenceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function upload(Request $r, string $project): JsonResponse
    {
        app(Access::class)->check($r, $project, 'files.upload');
        $r->validate(['entity_type' => 'sometimes|in:project,case,run_item,result,defect', 'entity_id' => 'sometimes|uuid', 'file' => 'required|file|max:20480|mimetypes:video/mp4,video/webm,application/pdf,image/jpeg,image/png,image/webp,text/plain,text/csv,application/zip,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        $type = $r->input('entity_type', 'project');
        $entity = $r->input('entity_id', $project);
        abort_if($type === 'result', 409, 'Recorded result evidence is immutable. Upload to the execution item before recording a result.');
        $section = app(EvidenceContext::class)->resolve($r, $project, $type, $entity, 'files.upload');
        $file = $r->file('file');
        $id = (string) Str::uuid();
        $name = mb_substr(str_replace(["\r", "\n", '/', '\\'], '_', $file->getClientOriginalName()), 0, 200);
        DB::transaction(function () use ($r, $project, $file, $id, $name, $type, $entity, $section) {
            DB::table('attachments')->insert(['id' => $id, 'project_id' => $project, 'owner_id' => $r->user()->id, 'entity_type' => $type, 'entity_id' => $entity, 'section_id' => $section, 'name' => $name, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now(), 'updated_at' => now()]);
            $stream = fopen($file->getRealPath(), 'rb');
            try {
                $position = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 262144);
                    if ($chunk !== false && $chunk !== '') {
                        DB::table('attachment_chunks')->insert(['attachment_id' => $id, 'position' => $position++, 'ciphertext' => Crypt::encryptString($chunk)]);
                    }
                }
            } finally {
                fclose($stream);
            }
        });

        return response()->json(['data' => ['id' => $id, 'name' => $name, 'size' => $file->getSize()]], 201);
    }

    public function index(Request $r, string $project): JsonResponse
    {
        app(Access::class)->check($r, $project, 'files.download');
        $v = $r->validate(['entity_type' => 'required|in:project,case,run_item,result,defect', 'entity_id' => 'required|uuid']);
        app(EvidenceContext::class)->resolve($r, $project, $v['entity_type'], $v['entity_id'], 'files.download');
        $query = DB::table('attachments')->where('project_id', $project)->where('entity_type', $v['entity_type'])->where('entity_id', $v['entity_id']);

        return response()->json(['data' => app(Access::class)->scope($query, $r, $project)->select('id', 'name', 'mime', 'size', 'sha256', 'owner_id', 'created_at')->orderBy('created_at')->get()]);
    }

    public function download(Request $r, string $project, string $file): StreamedResponse
    {
        app(Access::class)->check($r, $project, 'files.download');
        $f = DB::table('attachments')->where('id', $file)->where('project_id', $project)->first();
        abort_unless($f, 404);
        app(Access::class)->check($r, $project, 'files.download', $f->section_id);

        return response()->streamDownload(function () use ($file) {
            foreach (DB::table('attachment_chunks')->where('attachment_id', $file)->orderBy('position')->lazy(1) as $chunk) {
                echo Crypt::decryptString($chunk->ciphertext);
            }
        }, $f->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
