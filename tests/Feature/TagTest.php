<?php

use App\Actions\StoreExtractedRecipe;
use App\Extraction\Data\ExtractedIngredient;
use App\Extraction\Data\ExtractedRecipe;
use App\Extraction\Data\ExtractedStep;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Livewire\Volt\Volt;

test('ai-suggested tags are created and attached when a recipe is stored', function () {
    $user = User::factory()->create();

    $extracted = new ExtractedRecipe(
        title: 'Tomato Soup',
        description: null,
        servings: null,
        prepMinutes: null,
        cookMinutes: null,
        totalMinutes: null,
        difficulty: null,
        cuisine: null,
        ingredients: [ExtractedIngredient::fromArray(['name' => 'tomato'])],
        steps: [ExtractedStep::fromArray(['instruction' => 'Simmer.'])],
        tags: ['Soup', ' soup', 'Vegetarian', ''],
    );

    $recipe = app(StoreExtractedRecipe::class)($user, $extracted);

    expect($recipe->tags)->toHaveCount(2)
        ->and($recipe->tags->pluck('name')->all())->toEqualCanonicalizing(['soup', 'vegetarian']);

    expect(Tag::where('user_id', $user->id)->count())->toBe(2);
});

test('re-processing does not duplicate tags for the same user', function () {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['name' => 'soup']);

    $extracted = new ExtractedRecipe(
        title: 'Another Soup',
        description: null,
        servings: null,
        prepMinutes: null,
        cookMinutes: null,
        totalMinutes: null,
        difficulty: null,
        cuisine: null,
        ingredients: [ExtractedIngredient::fromArray(['name' => 'tomato'])],
        steps: [ExtractedStep::fromArray(['instruction' => 'Simmer.'])],
        tags: ['soup'],
    );

    app(StoreExtractedRecipe::class)($user, $extracted);

    expect(Tag::where('user_id', $user->id)->where('name', 'soup')->count())->toBe(1);
});

test('a user can manually set tags on a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.manage', ['recipe' => $recipe])
        ->set('tags', 'Quick, Easy, quick')
        ->call('save');

    expect($recipe->fresh()->tags->pluck('name')->all())->toEqualCanonicalizing(['quick', 'easy']);
});

test('editing a recipe pre-fills its existing tags', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'spicy']);
    $recipe->tags()->attach($tag);

    $this->actingAs($user);

    Volt::test('recipes.manage', ['recipe' => $recipe])
        ->assertSet('tags', 'spicy');
});

test('clearing the tags field removes all tags from a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'spicy']);
    $recipe->tags()->attach($tag);

    $this->actingAs($user);

    Volt::test('recipes.manage', ['recipe' => $recipe])
        ->set('tags', '')
        ->call('save');

    expect($recipe->fresh()->tags)->toBeEmpty();
});
