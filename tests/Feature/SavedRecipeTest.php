<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to login', function () {
    $this->get(route('saved.index'))->assertRedirect(route('login'));
});

test('a user can save and unsave a public recipe from the detail page', function () {
    $author = User::factory()->create();
    $recipe = Recipe::factory()->for($author)->create(['is_public' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])->call('save');

    expect($user->hasSaved($recipe))->toBeTrue();

    Volt::test('recipes.show', ['recipe' => $recipe])->call('unsave');

    expect($user->hasSaved($recipe))->toBeFalse();
});

test('a user cannot save their own recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['is_public' => true]);

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('save')
        ->assertForbidden();

    expect($user->hasSaved($recipe))->toBeFalse();
});

test('a user cannot save a private recipe belonging to someone else', function () {
    $recipe = Recipe::factory()->create(['is_public' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();
});

test('saving twice does not create duplicate rows', function () {
    $recipe = Recipe::factory()->create(['is_public' => true]);
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])->call('save');
    Volt::test('recipes.show', ['recipe' => $recipe])->call('save');

    $this->assertDatabaseCount('recipe_saves', 1);
});

test('a user can save and unsave a recipe from discover', function () {
    $recipe = Recipe::factory()->create(['is_public' => true, 'title' => 'Discoverable Soup']);
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('discover.index')
        ->call('save', $recipe->id)
        ->assertSee('Saved');

    expect($user->hasSaved($recipe))->toBeTrue();

    Volt::test('discover.index')->call('unsave', $recipe->id);

    expect($user->hasSaved($recipe))->toBeFalse();
});

test('the saved page lists recipes a user has bookmarked', function () {
    $author = User::factory()->create(['name' => 'Jane Cook']);
    $recipe = Recipe::factory()->for($author)->create(['title' => 'Jane\'s Soup', 'is_public' => true]);

    $user = User::factory()->create();
    $user->savedRecipes()->attach($recipe);

    $this->actingAs($user)
        ->get(route('saved.index'))
        ->assertOk()
        ->assertSee('Jane\'s Soup')
        ->assertSee('Jane Cook');
});

test('the saved page only shows recipes belonging to the current user', function () {
    $recipe = Recipe::factory()->create(['title' => 'Someone Elses Save', 'is_public' => true]);
    $otherUser = User::factory()->create();
    $otherUser->savedRecipes()->attach($recipe);

    $this->actingAs(User::factory()->create())
        ->get(route('saved.index'))
        ->assertOk()
        ->assertDontSee('Someone Elses Save');
});

test('a saved recipe that was unpublished disappears from the saved list without erroring', function () {
    $recipe = Recipe::factory()->create(['title' => 'Now Private Soup', 'is_public' => true]);
    $user = User::factory()->create();
    $user->savedRecipes()->attach($recipe);

    $recipe->makePrivate();

    $this->actingAs($user)
        ->get(route('saved.index'))
        ->assertOk()
        ->assertDontSee('Now Private Soup')
        ->assertSee('Nothing saved yet');
});

test('a saved recipe that was deleted is removed via cascade and does not error', function () {
    $recipe = Recipe::factory()->create(['is_public' => true]);
    $user = User::factory()->create();
    $user->savedRecipes()->attach($recipe);

    $recipe->delete();

    $this->assertDatabaseMissing('recipe_saves', ['recipe_id' => $recipe->id]);

    $this->actingAs($user)
        ->get(route('saved.index'))
        ->assertOk()
        ->assertSee('Nothing saved yet');
});

test('a user can remove a recipe from the saved page', function () {
    $recipe = Recipe::factory()->create(['is_public' => true, 'title' => 'Removable Soup']);
    $user = User::factory()->create();
    $user->savedRecipes()->attach($recipe);

    $this->actingAs($user);

    Volt::test('saved.index')
        ->call('unsave', $recipe->id)
        ->assertDontSee('Removable Soup');

    expect($user->hasSaved($recipe))->toBeFalse();
});
