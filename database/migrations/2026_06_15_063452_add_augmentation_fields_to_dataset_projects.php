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
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->boolean('augmentation_enabled')->default(false)->after('conversation_type');
            $table->string('augmentation_mode', 30)->nullable()->after('augmentation_enabled'); // similar, diverse, edge_cases, adversarial
            $table->decimal('augmentation_strength', 3, 2)->default(0.50)->after('augmentation_mode'); // 0.1–1.0
            $table->unsignedInteger('augmentation_target_count')->nullable()->after('augmentation_strength');
            $table->unsignedSmallInteger('augmentation_expand_percent')->nullable()->after('augmentation_target_count');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->decimal('source_similarity_score', 5, 4)->nullable()->after('evaluation_failed');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropColumn([
                'augmentation_enabled',
                'augmentation_mode',
                'augmentation_strength',
                'augmentation_target_count',
                'augmentation_expand_percent',
            ]);
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropColumn('source_similarity_score');
        });
    }
};
