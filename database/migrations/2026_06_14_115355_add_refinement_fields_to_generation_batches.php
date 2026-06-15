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
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->jsonb('provider_snapshot')->nullable()->after('error_message');
            $table->string('model_snapshot')->nullable()->after('provider_snapshot');
            $table->text('prompt_snapshot')->nullable()->after('model_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'provider_snapshot', 'model_snapshot', 'prompt_snapshot']);
        });
    }
};
