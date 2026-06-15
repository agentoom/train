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
            $table->boolean('conversation_enabled')->default(false)->after('negative_example_ratio');
            $table->unsignedTinyInteger('min_turns')->default(2)->after('conversation_enabled');
            $table->unsignedTinyInteger('max_turns')->default(6)->after('min_turns');
            $table->boolean('branching_enabled')->default(false)->after('max_turns');
            $table->string('conversation_type')->nullable()->after('branching_enabled');
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->jsonb('messages')->nullable()->after('failure_reason');
            $table->unsignedTinyInteger('turn_count')->nullable()->after('messages');
        });
    }

    public function down(): void
    {
        Schema::table('dataset_projects', function (Blueprint $table) {
            $table->dropColumn(['conversation_enabled', 'min_turns', 'max_turns', 'branching_enabled', 'conversation_type']);
        });

        Schema::table('dataset_rows', function (Blueprint $table) {
            $table->dropColumn(['messages', 'turn_count']);
        });
    }
};
