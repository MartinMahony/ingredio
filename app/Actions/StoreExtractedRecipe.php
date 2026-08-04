<?php

namespace App\Actions;

use App\Extraction\Data\ExtractedRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreExtractedRecipe
{
    /**
     * Persist an extracted recipe (and its ingredients and steps) for a user.
     *
     * @param  array<string, mixed>  $attributes  Extra recipe attributes (e.g. source_type, extracted_at).
     */
    public function __invoke(User $user, ExtractedRecipe $data, array $attributes = []): Recipe
    {
        return DB::transaction(function () use ($user, $data, $attributes): Recipe {
            $recipe = $user->recipes()->create([
                ...[
                    'title' => $data->title,
                    'description' => $data->description,
                    'servings' => $data->servings,
                    'prep_minutes' => $data->prepMinutes,
                    'cook_minutes' => $data->cookMinutes,
                    'total_minutes' => $data->totalMinutes ?? $this->sumMinutes($data),
                    'difficulty' => $data->difficulty,
                    'cuisine' => $data->cuisine,
                    'status' => 'ready',
                ],
                ...$attributes,
            ]);

            foreach ($data->ingredients as $position => $ingredient) {
                $recipe->ingredients()->create([
                    'group' => $ingredient->group,
                    'position' => $position,
                    'quantity' => $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'name' => $ingredient->name,
                    'note' => $ingredient->note,
                ]);
            }

            foreach ($data->steps as $position => $step) {
                $recipe->steps()->create([
                    'group' => $step->group,
                    'position' => $position,
                    'instruction' => $step->instruction,
                    'minutes' => $step->minutes,
                ]);
            }

            return $recipe;
        });
    }

    private function sumMinutes(ExtractedRecipe $data): ?int
    {
        if ($data->prepMinutes === null && $data->cookMinutes === null) {
            return null;
        }

        return (int) $data->prepMinutes + (int) $data->cookMinutes;
    }
}
