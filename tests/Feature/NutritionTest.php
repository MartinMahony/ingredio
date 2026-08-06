<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('a user can set nutrition values when creating a recipe', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('recipes.manage')
        ->set('title', 'Protein Bowl')
        ->set('calories', '450')
        ->set('protein_grams', '35.5')
        ->set('carbs_grams', '40')
        ->set('fat_grams', '12.2')
        ->call('save');

    $recipe = Recipe::firstWhere('title', 'Protein Bowl');

    expect($recipe->calories)->toBe(450)
        ->and($recipe->protein_grams)->toBe('35.5')
        ->and($recipe->carbs_grams)->toBe('40.0')
        ->and($recipe->fat_grams)->toBe('12.2')
        ->and($recipe->hasNutrition())->toBeTrue();
});

test('nutrition fields are optional and default to null', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('recipes.manage')
        ->set('title', 'No Nutrition Info')
        ->call('save');

    $recipe = Recipe::firstWhere('title', 'No Nutrition Info');

    expect($recipe->calories)->toBeNull()
        ->and($recipe->protein_grams)->toBeNull()
        ->and($recipe->carbs_grams)->toBeNull()
        ->and($recipe->fat_grams)->toBeNull()
        ->and($recipe->hasNutrition())->toBeFalse();
});

test('invalid nutrition values are rejected', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('recipes.manage')
        ->set('title', 'Bad Nutrition')
        ->set('calories', '-5')
        ->call('save')
        ->assertHasErrors('calories');

    expect(Recipe::where('title', 'Bad Nutrition')->exists())->toBeFalse();
});

test('editing a recipe pre-fills its existing nutrition values', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create([
        'calories' => 500,
        'protein_grams' => 20.5,
        'carbs_grams' => 60,
        'fat_grams' => 15,
    ]);

    $this->actingAs($user);

    Volt::test('recipes.manage', ['recipe' => $recipe])
        ->assertSet('calories', '500')
        ->assertSet('protein_grams', '20.5')
        ->assertSet('carbs_grams', '60.0')
        ->assertSet('fat_grams', '15.0');
});

test('nutrition is displayed on the recipe page when present', function () {
    $recipe = Recipe::factory()->create([
        'calories' => 320,
        'protein_grams' => 12.5,
        'carbs_grams' => 40.2,
        'fat_grams' => 8.1,
    ]);

    $this->actingAs($recipe->user)
        ->get(route('recipes.show', $recipe))
        ->assertSee('320 kcal')
        ->assertSee('12.5 g')
        ->assertSee('40.2 g')
        ->assertSee('8.1 g');
});

test('nutrition is hidden on the recipe page when absent', function () {
    $recipe = Recipe::factory()->create([
        'calories' => null,
        'protein_grams' => null,
        'carbs_grams' => null,
        'fat_grams' => null,
    ]);

    $this->actingAs($recipe->user)
        ->get(route('recipes.show', $recipe))
        ->assertDontSee('kcal')
        ->assertDontSee('Calories');
});

test('nutrition is displayed on the public shared recipe page', function () {
    $recipe = Recipe::factory()->create([
        'calories' => 320,
        'protein_grams' => 12.5,
    ]);
    $recipe->enableSharing();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertSee('320 kcal')
        ->assertSee('12.5 g');
});
