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
            $table->boolean('is_public')->default(false)->after('last_cooked_at');
            $table->timestamp('published_at')->nullable()->after('is_public');
            $table->index(['is_public', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'created_at']);
            $table->dropColumn(['is_public', 'published_at']);
        });
    }
};
