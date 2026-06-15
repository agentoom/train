<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->boolean('evaluation_enabled')->default(false)->after('replacement_generation_enabled');
            $table->foreignId('evaluation_ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete()->after('evaluation_enabled');
            $table->string('evaluation_model')->nullable()->after('evaluation_ai_provider_id');
            $table->unsignedTinyInteger('minimum_quality_score')->default(75)->after('evaluation_model');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_score')->nullable()->after('is_duplicate');
            $table->text('quality_reasoning')->nullable()->after('quality_score');
            $table->json('quality_issues')->nullable()->after('quality_reasoning');
            $table->boolean('evaluation_failed')->default(false)->after('quality_issues');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropForeign(['evaluation_ai_provider_id']);
            $table->dropColumn(['evaluation_enabled', 'evaluation_ai_provider_id', 'evaluation_model', 'minimum_quality_score']);
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropColumn(['quality_score', 'quality_reasoning', 'quality_issues', 'evaluation_failed']);
        });
    }
};
