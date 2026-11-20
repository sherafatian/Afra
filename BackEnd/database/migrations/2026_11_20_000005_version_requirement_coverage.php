<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_records', fn (Blueprint $table) => $table->boolean('archived')->default(false));
        Schema::create('requirement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('requirement_id')->constrained('project_records');
            $table->unsignedInteger('version');
            $table->foreignId('author_id')->nullable()->constrained('users');
            $table->string('title', 250);
            $table->json('content');
            $table->text('reason');
            $table->timestampTz('created_at');
            $table->unique(['requirement_id', 'version']);
        });
        // Preserve the current content of any earlier generic requirement records.
        DB::table('project_records')->where('kind', 'requirements')->orderBy('id')->chunk(100, function ($records) {
            foreach ($records as $record) {
                DB::table('requirement_revisions')->insert(['requirement_id' => $record->id, 'version' => $record->version, 'author_id' => null, 'title' => $record->title, 'content' => $record->content, 'reason' => 'Preserved from the earlier generic requirement record.', 'created_at' => $record->updated_at]);
            }
        });
        Schema::create('case_requirement_links', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('test_case_id')->constrained('test_cases');
            $table->unsignedInteger('case_version');
            $table->foreignUuid('requirement_id')->constrained('project_records');
            $table->unsignedInteger('requirement_version');
            $table->unique(['test_case_id', 'case_version', 'requirement_id'], 'case_requirement_identity');
            $table->index(['requirement_id', 'requirement_version']);
        });
        Schema::create('requirement_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('test_case_id')->constrained('test_cases');
            $table->unsignedInteger('case_version');
            $table->foreignUuid('requirement_id')->constrained('project_records');
            $table->unsignedInteger('requirement_version');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->text('reason');
            $table->timestampTz('created_at');
            $table->unique(['test_case_id', 'case_version', 'requirement_id', 'requirement_version'], 'requirement_compatibility_identity');
        });
        Schema::table('case_revisions', fn (Blueprint $table) => $table->json('review_requirements')->nullable());
        Schema::table('run_items', fn (Blueprint $table) => $table->json('requirement_bindings')->nullable());
    }

    public function down(): void
    {
        Schema::table('run_items', fn (Blueprint $table) => $table->dropColumn('requirement_bindings'));
        Schema::table('case_revisions', fn (Blueprint $table) => $table->dropColumn('review_requirements'));
        Schema::dropIfExists('requirement_compatibilities');
        Schema::dropIfExists('case_requirement_links');
        Schema::dropIfExists('requirement_revisions');
        Schema::table('project_records', fn (Blueprint $table) => $table->dropColumn('archived'));
    }
};
