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
        Schema::create('dataset_release_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_evaluation_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_project_id')->constrained()->cascadeOnDelete();
            $table->string('release_tag')->unique();
            $table->float('overall_score');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_release_versions');
    }
};
