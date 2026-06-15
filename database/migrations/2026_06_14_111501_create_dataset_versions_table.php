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
        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('model')->nullable();
            $table->text('system_prompt_snapshot')->nullable();
            $table->json('schema_snapshot')->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['dataset_project_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_versions');
    }
};
