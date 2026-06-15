<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generation_usages', function (Blueprint $table) {
            $table->jsonb('metadata')->nullable()->after('latency_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generation_usages', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
