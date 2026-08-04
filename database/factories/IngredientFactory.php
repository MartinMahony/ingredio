<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => 0,
            'quantity' => (string) fake()->numberBetween(1, 3),
            'unit' => fake()->randomElement(['g', 'ml', 'cup', 'tbsp', 'tsp', null]),
            'name' => fake()->word(),
            'note' => null,
        ];
    }
}
