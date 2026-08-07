<?php

use App\Actions\StoreExtractedRecipe;
use App\Enums\ScanStatus;
use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Data\ScanSource;
use App\Extraction\Drivers\GeminiRecipeExtractor;
use App\Extraction\Exceptions\RecipeExtractionException;
use App\Extraction\Support\UrlContentFetcher;
use App\Jobs\ProcessRecipeScan;
use App\Models\Recipe;
use App\Models\RecipeScan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $recipe
 * @return array<string, mixed>
 */
function fakeGeminiResponse(array $recipe): array
{
    return [
        'candidates' => [[
            'content' => [
                'parts' => [[
                    'text' => json_encode($recipe),
                ]],
            ],
        ]],
        'usageMetadata' => ['totalTokenCount' => 123],
    ];
}

/**
 * @return array<string, mixed>
 */
function sampleRecipePayload(): array
{
    return [
        'title' => 'Tomato Soup',
        'description' => 'A cozy classic.',
        'servings' => '4',
        'prep_minutes' => 10,
        'cook_minutes' => 20,
        'total_minutes' => 30,
        'difficulty' => 'Easy',
        'cuisine' => 'Italian',
        'ingredients' => [
            ['group' => null, 'quantity' => '2', 'unit' => 'cans', 'name' => 'tomatoes', 'note' => null],
            ['group' => null, 'quantity' => '1', 'unit' => null, 'name' => 'onion', 'note' => 'diced'],
            ['name' => ''],
        ],
        'steps' => [
            ['group' => null, 'instruction' => 'Sauté the onion.', 'minutes' => 5],
            ['group' => null, 'instruction' => 'Simmer with tomatoes.', 'minutes' => 20],
            ['instruction' => ''],
        ],
        'tags' => ['soup', 'vegetarian'],
        'calories' => 320,
        'protein_grams' => 12.5,
        'carbs_grams' => 40.2,
        'fat_grams' => 8.1,
    ];
}

beforeEach(function () {
    config()->set('scanning.keep_source', false);
});

it('processes a scan and stores the extracted recipe', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $user = User::factory()->create();
    Storage::disk('local')->put('scans/recipe.png', 'fake-image-bytes');

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );

    $scan->refresh();

    expect($scan->status)->toBe(ScanStatus::Ready)
        ->and($scan->recipe_id)->not->toBeNull();

    $recipe = Recipe::find($scan->recipe_id);

    expect($recipe->title)->toBe('Tomato Soup')
        ->and($recipe->user_id)->toBe($user->id)
        ->and($recipe->difficulty->value)->toBe('easy')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->steps)->toHaveCount(2)
        ->and($recipe->extracted_at)->not->toBeNull()
        ->and($recipe->calories)->toBe(320)
        ->and($recipe->protein_grams)->toBe('12.5')
        ->and($recipe->carbs_grams)->toBe('40.2')
        ->and($recipe->fat_grams)->toBe('8.1');

    Storage::disk('local')->assertMissing('scans/recipe.png');
});

it('leaves nutrition fields null when not stated in the source', function () {
    Storage::fake('local');

    $payload = sampleRecipePayload();
    unset($payload['calories'], $payload['protein_grams'], $payload['carbs_grams'], $payload['fat_grams']);

    Http::fake([
        '*' => Http::response(fakeGeminiResponse($payload)),
    ]);

    $user = User::factory()->create();
    Storage::disk('local')->put('scans/recipe.png', 'fake-image-bytes');

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );

    $recipe = Recipe::find($scan->refresh()->recipe_id);

    expect($recipe->calories)->toBeNull()
        ->and($recipe->protein_grams)->toBeNull()
        ->and($recipe->carbs_grams)->toBeNull()
        ->and($recipe->fat_grams)->toBeNull()
        ->and($recipe->hasNutrition())->toBeFalse();
});

it('retries when the stored file size does not match what was recorded at upload time', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $user = User::factory()->create();
    // Simulate a partially-synced file on a shared mount: only 5 of the
    // expected 20 bytes are actually readable yet.
    Storage::disk('local')->put('scans/recipe.png', 'fake-');

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
        'source_size' => 20,
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );
})->throws(RuntimeException::class, 'expected 20 bytes, read 5 bytes');

it('processes the file normally when the size matches what was recorded at upload time', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $user = User::factory()->create();
    $bytes = 'fake-image-bytes';
    Storage::disk('local')->put('scans/recipe.png', $bytes);

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
        'source_size' => strlen($bytes),
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );

    expect($scan->fresh()->status)->toBe(ScanStatus::Ready);
});

it('retains source_size compatibility with scans that predate this check', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $user = User::factory()->create();
    Storage::disk('local')->put('scans/recipe.png', 'fake-image-bytes');

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
        'source_size' => null,
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );

    expect($scan->fresh()->status)->toBe(ScanStatus::Ready);
});

it('retains the source file when keep_source is enabled', function () {
    config()->set('scanning.keep_source', true);

    Storage::fake('local');
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $user = User::factory()->create();
    Storage::disk('local')->put('scans/recipe.png', 'fake-image-bytes');

    $scan = RecipeScan::factory()->for($user)->create([
        'source_disk' => 'local',
        'source_path' => 'scans/recipe.png',
    ]);

    (new ProcessRecipeScan($scan))->handle(
        app(RecipeExtractor::class),
        app(StoreExtractedRecipe::class),
        app(UrlContentFetcher::class),
    );

    Storage::disk('local')->assertExists('scans/recipe.png');
    expect($scan->fresh()->source_kept)->toBeTrue();
});

it('marks the scan as failed when extraction throws', function () {
    $user = User::factory()->create();
    $scan = RecipeScan::factory()->for($user)->create();

    $scan->markFailed('boom');

    expect($scan->fresh()->status)->toBe(ScanStatus::Failed)
        ->and($scan->fresh()->error)->toBe('boom');
});

it('driver parses a Gemini response into an ExtractedRecipe', function () {
    Http::fake([
        '*' => Http::response(fakeGeminiResponse(sampleRecipePayload())),
    ]);

    $extractor = new GeminiRecipeExtractor('test-key', 'gemini-2.0-flash', 'https://example.test/v1beta', 30);

    $recipe = $extractor->extract(ScanSource::fromContents('bytes', 'image/png'));

    expect($recipe->title)->toBe('Tomato Soup')
        ->and($recipe->difficulty)->toBe('easy')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->steps)->toHaveCount(2)
        ->and($recipe->tags)->toBe(['soup', 'vegetarian']);

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), 'models/gemini-2.0-flash:generateContent')
            && data_get($request->data(), 'generationConfig.maxOutputTokens') === 2048;
    });
});

it('driver throws when the api key is missing', function () {
    $extractor = new GeminiRecipeExtractor('', 'gemini-2.0-flash', 'https://example.test/v1beta', 30);

    $extractor->extract(ScanSource::fromContents('bytes', 'image/png'));
})->throws(RecipeExtractionException::class);

it('driver throws when the response is not valid json', function () {
    Http::fake([
        '*' => Http::response(fakeGeminiResponse([])),
    ]);

    $extractor = new GeminiRecipeExtractor('test-key', 'gemini-2.0-flash', 'https://example.test/v1beta', 30);

    // Empty payload => no title => invalid payload.
    $extractor->extract(ScanSource::fromContents('bytes', 'image/png'));
})->throws(RecipeExtractionException::class);

it('driver throws when the http request fails', function () {
    Http::fake([
        '*' => Http::response('server error', 500),
    ]);

    $extractor = new GeminiRecipeExtractor('test-key', 'gemini-2.0-flash', 'https://example.test/v1beta', 30);

    $extractor->extract(ScanSource::fromContents('bytes', 'image/png'));
})->throws(RecipeExtractionException::class);
