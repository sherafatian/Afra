<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_items', function (Blueprint $table) {
            $table->foreignUuid('baseline_item_id')->nullable()->constrained('run_items');
            $table->index('baseline_item_id');
        });
        DB::statement('WITH RECURSIVE ancestry(root_id, item_id) AS (
            SELECT i.id, i.id FROM run_items i WHERE NOT EXISTS (SELECT 1 FROM retest_links l WHERE l.run_item_id = i.id)
            UNION ALL SELECT a.root_id, l.run_item_id FROM ancestry a JOIN defects d ON d.run_item_id = a.item_id JOIN retest_links l ON l.defect_id = d.id
        ) UPDATE run_items i SET baseline_item_id = a.root_id FROM ancestry a WHERE i.id = a.item_id AND a.root_id <> a.item_id');
        foreach (['test_results' => 'recorded_order', 'test_runs' => 'run_order'] as $name => $column) {
            DB::statement("ALTER TABLE {$name} ADD COLUMN {$column} BIGINT");
            DB::statement("UPDATE {$name} r SET {$column} = ordered.n FROM (SELECT id, row_number() OVER (ORDER BY created_at, id) AS n FROM {$name}) ordered WHERE r.id = ordered.id");
            DB::statement("CREATE SEQUENCE afra_{$column} AS BIGINT OWNED BY {$name}.{$column}");
            DB::select("SELECT setval('afra_{$column}', COALESCE(MAX({$column}), 1), COUNT(*) > 0) FROM {$name}");
            DB::statement("ALTER TABLE {$name} ALTER COLUMN {$column} SET DEFAULT nextval('afra_{$column}')");
            DB::statement("ALTER TABLE {$name} ALTER COLUMN {$column} SET NOT NULL");
            Schema::table($name, fn (Blueprint $table) => $table->unique($column));
        }
        Schema::table('test_runs', function (Blueprint $table) {
            $table->index(['project_id', 'release_id', 'plan_id', 'run_order'], 'release_plan_baseline_order');
            $table->index(['project_id', 'run_order']);
        });
        DB::statement("CREATE INDEX project_record_release_lookup ON project_records (project_id, kind, ((content->>'release_id'))) WHERE archived = false");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX project_record_release_lookup');
        Schema::table('test_results', fn (Blueprint $table) => $table->dropColumn('recorded_order'));
        Schema::table('test_runs', fn (Blueprint $table) => $table->dropColumn('run_order'));
        Schema::table('run_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('baseline_item_id'));
    }
};
