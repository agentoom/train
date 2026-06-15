<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->renameColumn('duplicates_replaced', 'regenerated');
        });
    }

    public function down(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->renameColumn('regenerated', 'duplicates_replaced');
        });
    }
};
