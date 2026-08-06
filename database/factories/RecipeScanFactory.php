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
            'model' => 'gemini-flash-latest',
            'source_kept' => false,
        ];
    }

    /**
     * @return Factory<RecipeScan>
     */
    public function url(string $url = 'https://example.test/recipe'): Factory
    {
        return $this->state([
            'source_type' => 'url',
            'source_url' => $url,
            'source_disk' => null,
            'source_path' => null,
            'original_filename' => null,
        ]);
    }
}
