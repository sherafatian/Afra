<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['run_items', 'attachments', 'audit_events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignUuid('section_id')->nullable()->constrained();
            });
        }
        Schema::table('attachments', function (Blueprint $t) {
            $t->string('entity_type')->default('project');
            $t->uuid('entity_id')->nullable();
            $t->index(['project_id', 'entity_type', 'entity_id']);
        });
        DB::statement('UPDATE run_items SET section_id = test_cases.section_id FROM test_cases WHERE run_items.test_case_id = test_cases.id');
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->dropIndex(['project_id', 'entity_type', 'entity_id']);
            $t->dropColumn(['entity_type', 'entity_id']);
        });
        foreach (['run_items', 'attachments', 'audit_events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('section_id');
            });
        }
    }
};
