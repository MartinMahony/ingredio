<?php

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Models\RecipeScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeScan>
 */
class RecipeScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipe_id' => null,
            'status' => ScanStatus::Pending,
            'source_type' => 'image',
            'source_disk' => 'local',
            'source_path' => 'scans/'.fake()->uuid().'.png',
            'original_filename' => 'recipe.png',
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'source_kept' => false,
        ];
    }
}
