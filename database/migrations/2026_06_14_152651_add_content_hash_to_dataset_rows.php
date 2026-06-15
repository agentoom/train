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
        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('is_valid');
            $table->boolean('is_duplicate')->default(false)->after('content_hash');
            $table->index(['dataset_version_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropIndex(['dataset_version_id', 'content_hash']);
            $table->dropColumn(['content_hash', 'is_duplicate']);
        });
    }
};
