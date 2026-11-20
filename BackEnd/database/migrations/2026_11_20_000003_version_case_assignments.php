<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_cases', function (Blueprint $t) {
            $t->unsignedInteger('assignment_version')->default(1);
            $t->foreignId('substitute_id')->nullable()->constrained('users');
        });
        Schema::table('run_items', function (Blueprint $t) {
            $t->foreignId('substitute_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('run_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('substitute_id');
        });
        Schema::table('test_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('substitute_id');
            $t->dropColumn('assignment_version');
        });
    }
};
