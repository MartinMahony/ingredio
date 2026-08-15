<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to login', function () {
    $this->get(route('discover.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the discover page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('discover.index'))
        ->assertOk()
        ->assertSee('Discover');
});

test('discover only lists public recipes', function () {
    $public = Recipe::factory()->create(['title' => 'Public Pancakes', 'is_public' => true]);
    $private = Recipe::factory()->create(['title' => 'Private Pancakes', 'is_public' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('discover.index'))
        ->assertSee('Public Pancakes')
        ->assertDontSee('Private Pancakes');
});

test('discover shows the recipe author', function () {
    $author = User::factory()->create(['name' => 'Jane Cook']);
    Recipe::factory()->for($author)->create(['title' => 'Jane\'s Soup', 'is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('discover.index'))
        ->assertSee('Jane\'s Soup')
        ->assertSee('Jane Cook');
});

test('discover recipes can be filtered by cuisine', function () {
    Recipe::factory()->create(['title' => 'Pasta', 'cuisine' => 'Italian', 'is_public' => true]);
    Recipe::factory()->create(['title' => 'Curry', 'cuisine' => 'Indian', 'is_public' => true]);

    $this->actingAs(User::factory()->create());

    $this->get(route('discover.index', ['cuisine' => 'Italian']))
        ->assertSee('Pasta')
        ->assertDontSee('Curry');
});

test('discover recipes can be searched by title or ingredient', function () {
    $withIngredient = Recipe::factory()->create(['title' => 'Soup', 'is_public' => true]);
    $withIngredient->ingredients()->create(['position' => 0, 'name' => 'Truffle Oil']);
    Recipe::factory()->create(['title' => 'Salad', 'is_public' => true]);

    $this->actingAs(User::factory()->create());

    Volt::test('discover.index')
        ->set('search', 'truffle')
        ->assertSee('Soup')
        ->assertDontSee('Salad');
});

test('discover results are paginated', function () {
    foreach (range(1, 15) as $i) {
        Recipe::factory()->create([
            'title' => "Recipe {$i} Soup",
            'is_public' => true,
            'published_at' => now()->subMinutes(15 - $i),
        ]);
    }

    $this->actingAs(User::factory()->create());

    $component = Volt::test('discover.index')
        ->set('sort', 'oldest')
        ->assertSee('Recipe 1 Soup')
        ->assertDontSee('Recipe 13 Soup');

    $component->call('nextPage')
        ->assertSee('Recipe 13 Soup')
        ->assertDontSee('Recipe 1 Soup');
});

test('discover excludes the current users own public recipes', function () {
    $user = User::factory()->create();
    $ownRecipe = Recipe::factory()->for($user)->create(['title' => 'My Own Public Soup', 'is_public' => true]);
    $othersRecipe = Recipe::factory()->create(['title' => 'Someone Elses Soup', 'is_public' => true]);

    $this->actingAs($user)
        ->get(route('discover.index'))
        ->assertDontSee('My Own Public Soup')
        ->assertSee('Someone Elses Soup');
});

test('discover never leaks a private recipes notes or owner-only actions', function () {
    $recipe = Recipe::factory()->create(['is_public' => true, 'notes' => 'Grandmas secret trick']);

    $this->actingAs(User::factory()->create())
        ->get(route('discover.index'))
        ->assertDontSee('Grandmas secret trick');
});
