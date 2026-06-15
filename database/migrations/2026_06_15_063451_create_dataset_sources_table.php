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
        Schema::create('dataset_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_project_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('source_type', 20); // json, jsonl, csv, txt, md, xlsx
            $table->jsonb('parsed_schema')->nullable();
            $table->integer('row_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_sources');
    }
};
