<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('active')->default(true);
            $t->boolean('is_system_admin')->default(false);
            $t->boolean('must_change_password')->default(true);
            $t->timestampTz('expires_at')->nullable();
            $t->unsignedInteger('auth_version')->default(1);
            $t->unsignedInteger('acl_version')->default(1);
            $t->json('profile')->nullable();
        });
        Schema::create('auth_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('refresh_hash', 64)->unique();
            $t->string('previous_hash', 64)->nullable()->index();
            $t->string('csrf_hash', 64);
            $t->timestampTz('expires_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestamps(6);
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('archived')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps(6);
        });
        Schema::create('project_members', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('project_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->json('grants')->nullable();
            $t->unique(['project_id', 'user_id']);
        });
        Schema::create('sections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->uuid('parent_id')->nullable();
            $t->string('name');
            $t->timestamps(6);
        });
        Schema::table('sections', function (Blueprint $t) {
            $t->foreign('parent_id')->references('id')->on('sections');
        });
        Schema::create('test_cases', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->foreignUuid('section_id')->nullable()->constrained('sections');
            $t->foreignId('author_id')->constrained('users');
            $t->foreignId('assignee_id')->nullable()->constrained('users');
            $t->string('title');
            $t->string('priority')->default('medium');
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->json('content');
            $t->timestamps(6);
            $t->index(['project_id', 'status', 'priority']);
        });
        Schema::create('case_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('test_case_id')->constrained();
            $t->unsignedInteger('version');
            $t->foreignId('author_id')->constrained('users');
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->json('snapshot');
            $t->string('status')->default('draft');
            $t->text('reason')->nullable();
            $t->timestamps(6);
            $t->unique(['test_case_id', 'version']);
        });
        Schema::create('project_records', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->string('kind');
            $t->string('title');
            $t->unsignedInteger('version')->default(1);
            $t->json('content');
            $t->timestamps(6);
            $t->index(['project_id', 'kind']);
        });
        Schema::create('test_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->string('title');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps(6);
        });
        Schema::create('run_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('test_run_id')->constrained();
            $t->foreignUuid('test_case_id')->constrained();
            $t->json('snapshot');
            $t->foreignId('assignee_id')->nullable()->constrained('users');
            $t->timestamps(6);
        });
        Schema::create('test_results', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('run_item_id')->constrained();
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('request_id')->unique();
            $t->string('outcome');
            $t->json('checks');
            $t->text('comment')->nullable();
            $t->timestamps(6);
            $t->index(['run_item_id', 'created_at']);
        });
        Schema::create('defects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('source_result_id')->constrained('test_results');
            $t->foreignUuid('project_id')->constrained();
            $t->foreignUuid('run_item_id')->constrained();
            $t->string('title');
            $t->text('description');
            $t->string('severity');
            $t->string('status')->default('open');
            $t->timestampTz('resolved_at', 6)->nullable();
            $t->string('closure_type')->nullable();
            $t->timestampTz('verified_at')->nullable();
            $t->json('history');
            $t->timestamps(6);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->nullable()->constrained();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('entity_id')->nullable();
            $t->json('details');
            $t->timestamps(6);
            $t->index(['project_id', 'created_at']);
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->timestamps(6);
        });
        Schema::create('attachment_chunks', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('attachment_id')->constrained();
            $t->unsignedInteger('position');
            $t->text('ciphertext');
            $t->unique(['attachment_id', 'position']);
        });
    }

    public function down(): void
    {
        foreach (['attachment_chunks', 'attachments', 'audit_events', 'defects', 'test_results', 'run_items', 'test_runs', 'project_records', 'case_revisions', 'test_cases', 'sections', 'project_members', 'projects', 'auth_sessions'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['active', 'is_system_admin', 'must_change_password', 'expires_at', 'auth_version', 'acl_version', 'profile']);
        });
    }
};
