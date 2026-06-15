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
        Schema::create('generation_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ai_provider_id', 'created_at']);
            $table->index('dataset_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_usages');
    }
};
