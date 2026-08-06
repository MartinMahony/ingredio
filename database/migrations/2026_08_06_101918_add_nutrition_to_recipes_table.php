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
            $table->unsignedSmallInteger('calories')->nullable()->after('cuisine');
            $table->decimal('protein_grams', 6, 1)->nullable()->after('calories');
            $table->decimal('carbs_grams', 6, 1)->nullable()->after('protein_grams');
            $table->decimal('fat_grams', 6, 1)->nullable()->after('carbs_grams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['calories', 'protein_grams', 'carbs_grams', 'fat_grams']);
        });
    }
};
