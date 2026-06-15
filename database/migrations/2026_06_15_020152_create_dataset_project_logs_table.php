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
        Schema::create('dataset_project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_project_logs');
    }
};
