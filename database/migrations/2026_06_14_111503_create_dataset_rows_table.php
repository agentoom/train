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
        Schema::create('dataset_rows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->json('payload');
            $table->boolean('is_valid')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->index(['dataset_version_id', 'is_valid', 'row_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_rows');
    }
};
