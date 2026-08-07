<?php

use App\Models\Collection;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('My Recipes');
});

test('the root redirects to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

test('recipes can be sorted', function () {
    $user = User::factory()->create();
    $first = Recipe::factory()->for($user)->create(['title' => 'First recipe', 'created_at' => now()->subDay()]);
    $second = Recipe::factory()->for($user)->create(['title' => 'Second recipe', 'created_at' => now()]);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->assertSeeInOrder([$second->title, $first->title]);

    Volt::test('dashboard')
        ->set('sort', 'oldest')
        ->assertSeeInOrder([$first->title, $second->title]);

    Volt::test('dashboard')
        ->set('sort', 'title_asc')
        ->assertSeeInOrder([$first->title, $second->title]);
});

test('recipes can be filtered by cuisine and tag', function () {
    $user = User::factory()->create();
    $italian = Recipe::factory()->for($user)->create(['title' => 'Pasta', 'cuisine' => 'Italian']);
    $indian = Recipe::factory()->for($user)->create(['title' => 'Curry', 'cuisine' => 'Indian']);
    $tag = $user->tags()->create(['name' => 'spicy']);
    $italian->tags()->attach($tag);

    $this->actingAs($user);

    $this->get(route('dashboard', ['cuisine' => 'Italian']))
        ->assertOk()
        ->assertSee('Pasta')
        ->assertDontSee('Curry');

    $this->get(route('dashboard', ['tag' => $tag->id]))
        ->assertOk()
        ->assertSee('Pasta')
        ->assertDontSee('Curry');
});

test('dashboard cards show ingredient and step counts', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->ingredients()->create(['position' => 0, 'name' => 'Flour']);
    $recipe->steps()->create(['position' => 0, 'instruction' => 'Mix']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('1 ingredient')
        ->assertSee('1 step');
});

test('recipes can be sorted by favorites and recently cooked', function () {
    $user = User::factory()->create();
    $favorite = Recipe::factory()->for($user)->create(['title' => 'Favorite', 'is_favorite' => true]);
    $plain = Recipe::factory()->for($user)->create(['title' => 'Plain']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('sort', 'favorite')
        ->assertSeeInOrder([$favorite->title, $plain->title]);

    $cooked = Recipe::factory()->for($user)->create(['title' => 'Cooked', 'last_cooked_at' => now()]);

    Volt::test('dashboard')
        ->set('sort', 'recently_cooked')
        ->assertSeeInOrder([$cooked->title, $favorite->title]);
});

test('a user can preview a recipe from the dashboard', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->ingredients()->create(['position' => 0, 'name' => 'Flour']);
    $recipe->steps()->create(['position' => 0, 'instruction' => 'Mix']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->call('openPreview', $recipe->id)
        ->assertSee('View full recipe')
        ->assertSee('Flour')
        ->assertSee('Mix');
});

test('a user can delete selected recipes in bulk', function () {
    $user = User::factory()->create();
    $first = Recipe::factory()->for($user)->create(['title' => 'First']);
    $second = Recipe::factory()->for($user)->create(['title' => 'Second']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('selected', [(string) $first->id, (string) $second->id])
        ->call('deleteSelected')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('recipes', ['id' => $first->id])
        ->assertDatabaseMissing('recipes', ['id' => $second->id]);
});

test('a user can add selected recipes to a collection in bulk', function () {
    $user = User::factory()->create();
    $collection = Collection::factory()->for($user)->create();
    $first = Recipe::factory()->for($user)->create();
    $second = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('selected', [(string) $first->id, (string) $second->id])
        ->set('bulkCollectionId', (string) $collection->id)
        ->call('addSelectedToCollection')
        ->assertHasNoErrors();

    expect($collection->fresh()->recipes->pluck('id')->all())->toContain($first->id, $second->id);
});
