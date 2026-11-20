<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->json('grants');
            $t->timestamps();
        });
        Schema::create('permission_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('permission_group_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->unique(['permission_group_id', 'user_id']);
        });
        Schema::create('section_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('section_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->string('effect');
            $t->unique(['section_id', 'user_id', 'action']);
        });
        Schema::create('retest_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('defect_id')->constrained();
            $t->foreignUuid('run_item_id')->unique()->constrained();
            $t->timestamps();
        });
        Schema::create('spent_refresh_tokens', function (Blueprint $t) {
            $t->string('hash', 64)->primary();
            $t->foreignUuid('session_id')->constrained('auth_sessions');
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach (['spent_refresh_tokens', 'retest_links', 'section_rules', 'permission_group_members', 'permission_groups'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
