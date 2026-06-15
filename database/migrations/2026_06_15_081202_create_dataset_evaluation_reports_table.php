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
        Schema::create('dataset_evaluation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained()->cascadeOnDelete();
            $table->float('overall_score');
            $table->boolean('passed')->default(false);
            $table->text('verdict');
            $table->jsonb('evaluator_scores')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->float('duplicate_rate')->default(0.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_evaluation_reports');
    }
};
