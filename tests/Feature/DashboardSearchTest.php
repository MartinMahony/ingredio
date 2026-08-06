<?php

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Livewire\Volt\Volt;

test('search filters recipes by title', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Tomato Soup']);
    Recipe::factory()->for($user)->create(['title' => 'Beef Stew']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('search', 'tomato')
        ->assertSee('Tomato Soup')
        ->assertDontSee('Beef Stew');
});

test('search filters recipes by ingredient name', function () {
    $user = User::factory()->create();
    $soup = Recipe::factory()->for($user)->create(['title' => 'Tomato Soup']);
    $soup->ingredients()->create(['position' => 0, 'name' => 'Tomato']);
    $stew = Recipe::factory()->for($user)->create(['title' => 'Beef Stew']);
    $stew->ingredients()->create(['position' => 0, 'name' => 'Beef']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('search', 'beef')
        ->assertSee('Beef Stew')
        ->assertDontSee('Tomato Soup');
});

test('cuisine filter narrows the recipe list', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Pasta', 'cuisine' => 'Italian']);
    Recipe::factory()->for($user)->create(['title' => 'Tacos', 'cuisine' => 'Mexican']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('cuisine', 'Mexican')
        ->assertSee('Tacos')
        ->assertDontSee('Pasta');
});

test('tag filter narrows the recipe list', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'vegetarian']);
    $veggie = Recipe::factory()->for($user)->create(['title' => 'Veggie Bowl']);
    $veggie->tags()->attach($tag);
    Recipe::factory()->for($user)->create(['title' => 'Beef Stew']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('tag', (string) $tag->id)
        ->assertSee('Veggie Bowl')
        ->assertDontSee('Beef Stew');
});

test('search only matches the current users recipes', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'My Tomato Soup']);
    Recipe::factory()->create(['title' => 'Their Tomato Soup']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('search', 'tomato')
        ->assertSee('My Tomato Soup')
        ->assertDontSee('Their Tomato Soup');
});

test('clearing filters restores the full list', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Tomato Soup']);
    Recipe::factory()->for($user)->create(['title' => 'Beef Stew']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->set('search', 'tomato')
        ->call('clearFilters')
        ->assertSee('Tomato Soup')
        ->assertSee('Beef Stew');
});
