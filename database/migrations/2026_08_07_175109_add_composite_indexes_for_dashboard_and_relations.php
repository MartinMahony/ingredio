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
        Schema::table('recipes', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'cuisine']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->index(['recipe_id', 'position']);
            $table->index(['recipe_id', 'name']);
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->index(['recipe_id', 'position']);
        });

        Schema::table('recipe_tag', function (Blueprint $table) {
            $table->index(['tag_id', 'recipe_id']);
        });

        Schema::table('recipe_scans', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id', 'cuisine']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['recipe_id', 'position']);
            $table->dropIndex(['recipe_id', 'name']);
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->dropIndex(['recipe_id', 'position']);
        });

        Schema::table('recipe_tag', function (Blueprint $table) {
            $table->dropIndex(['tag_id', 'recipe_id']);
        });

        Schema::table('recipe_scans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });
    }
};
