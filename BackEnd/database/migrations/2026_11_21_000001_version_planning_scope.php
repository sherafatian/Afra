<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_records', fn (Blueprint $table) => $table->foreignId('owner_id')->nullable()->constrained('users'));
        Schema::create('project_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('record_id')->constrained('project_records');
            $table->unsignedInteger('version');
            $table->foreignId('author_id')->nullable()->constrained('users');
            $table->json('snapshot');
            $table->timestampTz('created_at');
            $table->unique(['record_id', 'version']);
        });
        DB::table('project_records')->where('kind', '!=', 'requirements')->orderBy('id')->chunk(100, function ($records) {
            foreach ($records as $record) {
                DB::table('project_record_revisions')->insert(['record_id' => $record->id, 'version' => $record->version, 'author_id' => null, 'snapshot' => json_encode(['title' => $record->title, 'content' => json_decode($record->content, true)]), 'created_at' => $record->updated_at]);
            }
        });
        Schema::create('execution_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('token_hash', 64)->unique();
            $table->json('source');
            $table->string('fingerprint', 64);
            $table->string('confirmation_hash', 64)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->foreignUuid('run_id')->nullable()->constrained('test_runs');
        });
        Schema::table('test_runs', function (Blueprint $table) {
            $table->json('scope_snapshot')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('project_records');
            $table->foreignUuid('release_id')->nullable()->constrained('project_records');
        });
        Schema::table('run_items', function (Blueprint $table) {
            $table->boolean('required')->default(true);
            $table->string('variant_key', 64)->nullable();
            $table->json('configuration')->nullable();
            $table->json('data_snapshot')->nullable();
            $table->unique(['test_run_id', 'variant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_previews');
        Schema::table('run_items', function (Blueprint $table) {
            $table->dropUnique(['test_run_id', 'variant_key']);
            $table->dropColumn(['required', 'variant_key', 'configuration', 'data_snapshot']);
        });
        Schema::table('test_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropConstrainedForeignId('release_id');
            $table->dropColumn('scope_snapshot');
        });
        Schema::dropIfExists('project_record_revisions');
        Schema::table('project_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('owner_id'));
    }
};
