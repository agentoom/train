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
            $table->unsignedInteger('chunk_size')->default(10)->after('record_count');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropColumn('chunk_size');
        });
    }
};
