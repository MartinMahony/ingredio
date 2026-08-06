<?php

use App\Models\Collection;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access collection pages', function () {
    $this->get(route('collections.index'))->assertRedirect(route('login'));
});

test('a user can create a collection', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('collections.index')
        ->set('name', 'Weeknight dinners')
        ->set('description', 'Fast meals')
        ->call('create')
        ->assertHasNoErrors();

    expect(Collection::sole())
        ->name->toBe('Weeknight dinners')
        ->description->toBe('Fast meals');
});

test('a user cannot create two collections with the same name', function () {
    $user = User::factory()->create();
    Collection::factory()->for($user)->create(['name' => 'Favourites']);

    $this->actingAs($user);

    Volt::test('collections.index')
        ->set('name', 'Favourites')
        ->call('create')
        ->assertHasErrors('name');
});

test('a user can add a recipe to a collection from the recipe page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $collection = Collection::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->set('selectedCollectionId', (string) $collection->id)
        ->call('addToCollection');

    expect($collection->fresh()->recipes->pluck('id')->all())->toBe([$recipe->id]);
});

test('a user can remove a recipe from a collection', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $collection = Collection::factory()->for($user)->create();
    $collection->recipes()->attach($recipe);

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('removeFromCollection', $collection->id);

    expect($collection->fresh()->recipes)->toBeEmpty();
});

test('a user cannot add a recipe to another users collection', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $collection = Collection::factory()->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->set('selectedCollectionId', (string) $collection->id)
        ->call('addToCollection')
        ->assertForbidden();
});

test('a user can view a collection and its recipes', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->for($user)->create(['name' => 'Favourites']);
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Pancakes']);
    $collection->recipes()->attach($recipe);

    $this->actingAs($user)
        ->get(route('collections.show', $collection))
        ->assertOk()
        ->assertSee('Favourites')
        ->assertSee('Pancakes');
});

test('a user cannot view another users collection', function () {
    $collection = Collection::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('collections.show', $collection))
        ->assertForbidden();
});

test('a user can delete their own collection', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('collections.show', ['collection' => $collection])
        ->call('delete')
        ->assertRedirect(route('collections.index'));

    $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
});

test('deleting a collection does not delete its recipes', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $collection->recipes()->attach($recipe);

    $this->actingAs($user);

    Volt::test('collections.show', ['collection' => $collection])->call('delete');

    $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
});
