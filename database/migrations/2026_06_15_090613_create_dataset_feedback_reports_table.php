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
        Schema::create('dataset_feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_run_id')
                ->constrained('dataset_evaluation_reports')
                ->cascadeOnDelete();
            $table->json('failure_analysis_json');
            $table->json('improvement_signals_json');
            $table->float('system_health_score');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_feedback_reports');
    }
};
