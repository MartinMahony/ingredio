<?php

namespace App\Actions;

use App\Extraction\Data\ExtractedRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// @phpstan-ignore-next-line

class StoreExtractedRecipe
{
    public function __construct(private readonly SyncRecipeTags $syncTags) {}

    /**
     * Persist an extracted recipe (and its ingredients, steps, and tags) for a user.
     *
     * @param  array<string, mixed>  $attributes  Extra recipe attributes (e.g. source_type, extracted_at).
     */
    public function __invoke(User $user, ExtractedRecipe $data, array $attributes = []): Recipe
    {
        return DB::transaction(function () use ($user, $data, $attributes): Recipe {
            $recipe = $user->recipes()->create([
                ...[
                    'title' => $this->normalizeTitle($data->title),
                    'description' => $data->description,
                    'servings' => $data->servings,
                    'prep_minutes' => $data->prepMinutes,
                    'cook_minutes' => $data->cookMinutes,
                    'total_minutes' => $data->totalMinutes ?? $this->sumMinutes($data),
                    'difficulty' => $data->difficulty,
                    'cuisine' => $data->cuisine,
                    'calories' => $data->calories,
                    'protein_grams' => $data->proteinGrams,
                    'carbs_grams' => $data->carbsGrams,
                    'fat_grams' => $data->fatGrams,
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

            ($this->syncTags)($user, $recipe, $data->tags);

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

    /**
     * Normalize scanned titles to Title Case, since sources vary wildly
     * (ALL CAPS, all lowercase, etc.) and shouldn't be trusted verbatim.
     */
    private function normalizeTitle(string $title): string
    {
        return Str::title(Str::squish($title));
    }
}
