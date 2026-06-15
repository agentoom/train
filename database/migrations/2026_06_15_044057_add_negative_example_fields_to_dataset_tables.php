<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('negative_example_ratio')->default(0)->after('refiner_model');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->string('expected_behavior')->nullable()->after('refined_by');
            $table->string('failure_reason')->nullable()->after('expected_behavior');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropColumn('negative_example_ratio');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropColumn(['expected_behavior', 'failure_reason']);
        });
    }
};
