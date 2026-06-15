<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->boolean('critic_enabled')->default(false)->after('minimum_quality_score');
            $table->foreignId('critic_ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete()->after('critic_enabled');
            $table->string('critic_model')->nullable()->after('critic_ai_provider_id');
            $table->boolean('refiner_enabled')->default(false)->after('critic_model');
            $table->foreignId('refiner_ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete()->after('refiner_enabled');
            $table->string('refiner_model')->nullable()->after('refiner_ai_provider_id');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->string('generated_by')->nullable()->after('evaluation_failed');
            $table->text('critic_feedback')->nullable()->after('generated_by');
            $table->string('refined_by')->nullable()->after('critic_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropForeign(['critic_ai_provider_id']);
            $table->dropForeign(['refiner_ai_provider_id']);
            $table->dropColumn(['critic_enabled', 'critic_ai_provider_id', 'critic_model', 'refiner_enabled', 'refiner_ai_provider_id', 'refiner_model']);
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropColumn(['generated_by', 'critic_feedback', 'refined_by']);
        });
    }
};
