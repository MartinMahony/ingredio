<?php

namespace App\Extraction\Data;

use App\Enums\RecipeDifficulty;
use App\Extraction\Exceptions\RecipeExtractionException;

final class ExtractedRecipe
{
    /**
     * @param  list<ExtractedIngredient>  $ingredients
     * @param  list<ExtractedStep>  $steps
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $servings,
        public readonly ?int $prepMinutes,
        public readonly ?int $cookMinutes,
        public readonly ?int $totalMinutes,
        public readonly ?string $difficulty,
        public readonly ?string $cuisine,
        public readonly array $ingredients,
        public readonly array $steps,
        public readonly array $tags = [],
        public readonly ?int $calories = null,
        public readonly ?float $proteinGrams = null,
        public readonly ?float $carbsGrams = null,
        public readonly ?float $fatGrams = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RecipeExtractionException
     */
    public static function fromArray(array $data): self
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw RecipeExtractionException::invalidPayload('The extracted recipe has no title.');
        }

        $ingredients = array_values(array_filter(
            array_map(
                fn ($row): ExtractedIngredient => ExtractedIngredient::fromArray((array) $row),
                is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [],
            ),
            fn (ExtractedIngredient $ingredient): bool => $ingredient->name !== '',
        ));

        $steps = array_values(array_filter(
            array_map(
                fn ($row): ExtractedStep => ExtractedStep::fromArray((array) $row),
                is_array($data['steps'] ?? null) ? $data['steps'] : [],
            ),
            fn (ExtractedStep $step): bool => $step->instruction !== '',
        ));

        $tags = array_values(array_filter(array_map(
            fn ($tag): string => trim((string) $tag),
            is_array($data['tags'] ?? null) ? $data['tags'] : [],
        )));

        return new self(
            title: $title,
            description: Normalize::nullableString($data['description'] ?? null),
            servings: Normalize::nullableString($data['servings'] ?? null),
            prepMinutes: Normalize::nullableInt($data['prep_minutes'] ?? null),
            cookMinutes: Normalize::nullableInt($data['cook_minutes'] ?? null),
            totalMinutes: Normalize::nullableInt($data['total_minutes'] ?? null),
            difficulty: self::normalizeDifficulty($data['difficulty'] ?? null),
            cuisine: Normalize::nullableString($data['cuisine'] ?? null),
            ingredients: $ingredients,
            steps: $steps,
            tags: $tags,
            calories: Normalize::nullableInt($data['calories'] ?? null),
            proteinGrams: Normalize::nullableFloat($data['protein_grams'] ?? null),
            carbsGrams: Normalize::nullableFloat($data['carbs_grams'] ?? null),
            fatGrams: Normalize::nullableFloat($data['fat_grams'] ?? null),
        );
    }

    private static function normalizeDifficulty(mixed $value): ?string
    {
        $value = Normalize::nullableString($value);

        if ($value === null) {
            return null;
        }

        return RecipeDifficulty::tryFrom(strtolower($value))?->value;
    }
}
