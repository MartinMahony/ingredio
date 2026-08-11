<?php

use App\Models\Recipe;
use App\Models\User;

test('it normalizes scanned recipe titles to Title Case', function () {
    $user = User::factory()->create();
    $scanned = Recipe::factory()->for($user)->create(['title' => 'THAI RED CHICKEN CURRY', 'source_type' => 'image']);
    $urlScanned = Recipe::factory()->for($user)->create(['title' => 'web pancakes', 'source_type' => 'url']);
    $alreadyGood = Recipe::factory()->for($user)->create(['title' => 'Tomato Soup', 'source_type' => 'image']);
    $manual = Recipe::factory()->for($user)->create(['title' => 'MY special recipe', 'source_type' => 'manual']);

    $this->artisan('app:normalize-recipe-titles')->assertSuccessful();

    expect($scanned->fresh()->title)->toBe('Thai Red Chicken Curry')
        ->and($urlScanned->fresh()->title)->toBe('Web Pancakes')
        ->and($alreadyGood->fresh()->title)->toBe('Tomato Soup')
        ->and($manual->fresh()->title)->toBe('MY special recipe');
});

test('the dry-run option does not persist changes', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['title' => 'THAI RED CHICKEN CURRY', 'source_type' => 'image']);

    $this->artisan('app:normalize-recipe-titles', ['--dry-run' => true])->assertSuccessful();

    expect($recipe->fresh()->title)->toBe('THAI RED CHICKEN CURRY');
});
