<?php

namespace Database\Factories;

use App\Enums\RecipeDifficulty;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prep = fake()->numberBetween(5, 30);
        $cook = fake()->numberBetween(10, 60);

        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'servings' => (string) fake()->numberBetween(1, 8),
            'prep_minutes' => $prep,
            'cook_minutes' => $cook,
            'total_minutes' => $prep + $cook,
            'difficulty' => fake()->randomElement(RecipeDifficulty::cases()),
            'cuisine' => fake()->randomElement(['Italian', 'Mexican', 'Indian', 'Thai', 'French']),
            'source_type' => 'manual',
            'status' => 'ready',
        ];
    }
}
