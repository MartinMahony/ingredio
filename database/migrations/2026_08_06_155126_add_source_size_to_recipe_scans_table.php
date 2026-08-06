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
        Schema::table('recipe_scans', function (Blueprint $table) {
            $table->unsignedInteger('source_size')->nullable()->after('source_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_scans', function (Blueprint $table) {
            $table->dropColumn('source_size');
        });
    }
};
