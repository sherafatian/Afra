<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $aliases = ['view' => ['project.view'], 'projects.update' => ['project.update'], 'files.upload' => ['attachments.upload'], 'files.download' => ['attachments.read'], 'cases.archive' => ['cases.delete'], 'defects.retest' => ['runs.retest'], 'defects.manage' => ['defects.update', 'defects.transition', 'defects.close'], 'requirements.manage' => ['requirements.create', 'requirements.update', 'requirements.delete'], 'plans.manage' => ['plans.create', 'plans.update', 'plans.delete'], 'shared-steps.manage' => ['shared.manage'], 'saved-views.manage' => ['views.manage'], 'custom-fields.manage' => ['fields.manage'], 'configuration-profiles.manage' => ['profiles.create', 'profiles.update'], 'datasets.manage' => ['datasets.create', 'datasets.update'], 'builds.manage' => ['builds.create', 'builds.update']];
        foreach (['project_members', 'permission_groups'] as $table) {
            DB::table($table)->select('id', 'grants')->orderBy('id')->chunkById(250, function ($rows) use ($table, $aliases) {
                foreach ($rows as $row) {
                    $normalized = [];
                    foreach (json_decode($row->grants ?? '{}', true) as $key => $effect) {
                        $effect = $effect === true ? 'allow' : ($effect === false ? 'deny' : $effect);
                        foreach ($aliases[$key] ?? [$key] as $canonical) {
                            if (($normalized[$canonical] ?? null) !== 'deny') {
                                $normalized[$canonical] = $effect;
                            }
                        }
                    }DB::table($table)->where('id', $row->id)->update(['grants' => json_encode($normalized)]);
                }
            });
        }
        Schema::table('project_members', function (Blueprint $t) {
            $t->index(['user_id', 'project_id']);
        });
        Schema::table('permission_group_members', function (Blueprint $t) {
            $t->index(['user_id', 'permission_group_id']);
        });
        Schema::table('permission_groups', function (Blueprint $t) {
            $t->index(['project_id', 'active']);
        });
        Schema::table('section_rules', function (Blueprint $t) {
            $t->index(['user_id', 'section_id']);
        });
        Schema::table('auth_sessions', function (Blueprint $t) {
            $t->index(['user_id', 'revoked_at']);
        });
        Schema::table('test_cases', function (Blueprint $t) {
            $t->index(['project_id', 'section_id']);
        });
        Schema::table('run_items', function (Blueprint $t) {
            $t->index(['test_run_id', 'section_id']);
        });
    }

    public function down(): void
    {
        foreach (['project_members' => ['user_id', 'project_id'], 'permission_group_members' => ['user_id', 'permission_group_id'], 'permission_groups' => ['project_id', 'active'], 'section_rules' => ['user_id', 'section_id'], 'auth_sessions' => ['user_id', 'revoked_at'], 'test_cases' => ['project_id', 'section_id'], 'run_items' => ['test_run_id', 'section_id']] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                $t->dropIndex($columns);
            });
        }
    }
};
