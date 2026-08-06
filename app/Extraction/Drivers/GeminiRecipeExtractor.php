<?php

namespace App\Extraction\Drivers;

use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Data\ExtractedRecipe;
use App\Extraction\Data\ScanSource;
use App\Extraction\Exceptions\RecipeExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiRecipeExtractor implements RecipeExtractor
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function extract(ScanSource $source): ExtractedRecipe
    {
        if (trim($this->apiKey) === '') {
            throw RecipeExtractionException::missingApiKey();
        }

        $endpoint = sprintf('%s/models/%s:generateContent', rtrim($this->baseUrl, '/'), $this->model);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->post($endpoint, $this->payload($source));
        } catch (ConnectionException $e) {
            throw RecipeExtractionException::requestFailed($e->getMessage());
        }

        if ($response->failed()) {
            throw RecipeExtractionException::requestFailed('HTTP '.$response->status().' '.$response->body());
        }

        return ExtractedRecipe::fromArray($this->decodeCandidate($response->json()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ScanSource $source): array
    {
        return [
            'contents' => [[
                'parts' => [
                    ['text' => $this->prompt($source)],
                    $source->isText
                        ? ['text' => $source->contents]
                        : ['inline_data' => [
                            'mime_type' => $source->mimeType,
                            'data' => $source->base64(),
                        ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->schema(),
                'temperature' => 0.1,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function decodeCandidate(?array $body): array
    {
        $text = data_get($body, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw RecipeExtractionException::invalidPayload('The model returned no content.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw RecipeExtractionException::invalidPayload('The model response was not valid JSON.');
        }

        return $decoded;
    }

    private function prompt(ScanSource $source): string
    {
        $subject = $source->isText
            ? 'The following text was extracted from a recipe webpage'
            : 'The attached file is a screenshot, photo, or PDF of a single cooking recipe';

        return <<<PROMPT
        You are a precise recipe extraction assistant. {$subject}. Extract the recipe
        into the required JSON structure.

        Rules:
        - Transcribe values exactly as written. Do not invent, translate, or add ingredients or steps.
        - Ignore navigation menus, ads, comments, or other content unrelated to the recipe itself.
        - Keep ingredient quantities and units separate from the ingredient name (e.g. quantity "2",
          unit "cups", name "flour").
        - Preserve any ingredient section headings (e.g. "For the sauce") in the "group" field.
        - Split the method into individual sequential steps.
        - Use integer minutes for prep, cook, and per-step timings where stated; otherwise null.
        - Set difficulty only if clearly stated, as one of: easy, medium, hard.
        - Suggest up to 5 short lowercase tags (cuisine, meal type, dietary) in "tags".
        - If the source states nutritional information (calories, protein, carbs, fat),
          include it as per-serving values in "calories" (whole kcal), "protein_grams",
          "carbs_grams", and "fat_grams" (grams, one decimal place). Only transcribe values
          that are explicitly stated — never calculate, estimate, or infer nutrition yourself.
        - If a field is not present in the source, use null (or an empty array for lists).
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $nullableString = ['type' => 'string', 'nullable' => true];
        $nullableInteger = ['type' => 'integer', 'nullable' => true];
        $nullableNumber = ['type' => 'number', 'nullable' => true];

        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => $nullableString,
                'servings' => $nullableString,
                'prep_minutes' => $nullableInteger,
                'cook_minutes' => $nullableInteger,
                'total_minutes' => $nullableInteger,
                'difficulty' => ['type' => 'string', 'nullable' => true, 'enum' => ['easy', 'medium', 'hard']],
                'cuisine' => $nullableString,
                'ingredients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'group' => $nullableString,
                            'quantity' => $nullableString,
                            'unit' => $nullableString,
                            'name' => ['type' => 'string'],
                            'note' => $nullableString,
                        ],
                        'required' => ['name'],
                    ],
                ],
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'group' => $nullableString,
                            'instruction' => ['type' => 'string'],
                            'minutes' => $nullableInteger,
                        ],
                        'required' => ['instruction'],
                    ],
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'calories' => $nullableInteger,
                'protein_grams' => $nullableNumber,
                'carbs_grams' => $nullableNumber,
                'fat_grams' => $nullableNumber,
            ],
            'required' => ['title', 'ingredients', 'steps'],
        ];
    }
}
