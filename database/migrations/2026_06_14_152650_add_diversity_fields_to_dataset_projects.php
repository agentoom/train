<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->json('diversity_dimensions')->nullable()->after('metadata');
            $table->string('generation_seed')->nullable()->after('diversity_dimensions');
            $table->string('uniqueness_level')->default('balanced')->after('generation_seed');
            $table->boolean('semantic_deduplication_enabled')->default(true)->after('uniqueness_level');
            $table->decimal('semantic_similarity_threshold', 4, 2)->default(0.92)->after('semantic_deduplication_enabled');
            $table->boolean('replacement_generation_enabled')->default(true)->after('semantic_similarity_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropColumn([
                'diversity_dimensions',
                'generation_seed',
                'uniqueness_level',
                'semantic_deduplication_enabled',
                'semantic_similarity_threshold',
                'replacement_generation_enabled',
            ]);
        });
    }
};
