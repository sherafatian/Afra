<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_roles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->json('grants');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps(6);
            $t->unique(['project_id', 'name']);
        });
        Schema::table('project_members', function (Blueprint $t) {
            $t->foreignUuid('custom_role_id')->nullable()->constrained('project_roles');
            $t->unsignedInteger('version')->default(1);
        });
        Schema::table('permission_groups', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
        });
        Schema::table('section_rules', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('section_rules', function (Blueprint $t) {
            $t->dropColumn('version');
        });
        Schema::table('permission_groups', function (Blueprint $t) {
            $t->dropColumn('version');
        });
        Schema::table('project_members', function (Blueprint $t) {
            $t->dropConstrainedForeignId('custom_role_id');
            $t->dropColumn('version');
        });
        Schema::dropIfExists('project_roles');
    }
};
