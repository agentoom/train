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
        Schema::table('dataset_rows', function (Blueprint $table): void {
            // Composite index for the most common query patterns in DatasetDetail
            $table->index(
                ['dataset_version_id', 'is_duplicate', 'is_valid'],
                'dataset_rows_version_dup_valid_index'
            );

            // Index for evaluation filtering
            $table->index(
                ['dataset_version_id', 'evaluation_failed'],
                'dataset_rows_version_eval_failed_index'
            );

            // Index for quality score aggregation
            $table->index(
                ['dataset_version_id', 'quality_score', 'is_duplicate'],
                'dataset_rows_version_quality_index'
            );

            // Index for conversation/batch relationship lookups
            $table->index(
                ['dataset_version_id', 'expected_behavior'],
                'dataset_rows_version_behavior_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_rows', function (Blueprint $table): void {
            $table->dropIndex('dataset_rows_version_dup_valid_index');
            $table->dropIndex('dataset_rows_version_eval_failed_index');
            $table->dropIndex('dataset_rows_version_quality_index');
            $table->dropIndex('dataset_rows_version_behavior_index');
        });
    }
};
