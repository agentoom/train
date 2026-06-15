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
        Schema::table('dataset_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('generation_seed')->nullable()->after('record_count');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->jsonb('metadata')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_versions', function (Blueprint $table) {
            $table->dropColumn(['generation_seed', 'cancelled_at', 'metadata']);
        });
    }
};
