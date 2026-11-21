<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_record_revisions', function (Blueprint $table) {
            $table->string('review_status', 20)->default('draft');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->text('review_reason')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_record_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['review_status', 'review_reason', 'reviewed_at']);
        });
    }
};
