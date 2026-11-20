<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_results', function (Blueprint $t) {
            $t->json('evidence_ids')->nullable();
            $t->string('actor_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('test_results', function (Blueprint $t) {
            $t->dropColumn(['evidence_ids', 'actor_name']);
        });
    }
};
