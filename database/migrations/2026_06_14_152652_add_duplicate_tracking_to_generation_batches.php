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
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->unsignedInteger('duplicates_detected')->default(0)->after('tokens_used');
            $table->unsignedInteger('duplicates_replaced')->default(0)->after('duplicates_detected');
            $table->boolean('partially_completed')->default(false)->after('duplicates_replaced');
        });
    }

    public function down(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->dropColumn(['duplicates_detected', 'duplicates_replaced', 'partially_completed']);
        });
    }
};
