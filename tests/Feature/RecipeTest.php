<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access recipe pages', function () {
    $this->get(route('recipes.create'))->assertRedirect(route('login'));
});

test('a user can view the create recipe form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('recipes.create'))
        ->assertOk()
        ->assertSee('New recipe');
});

test('a user can create a recipe with ingredients and steps', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('recipes.manage')
        ->set('title', 'Tomato Soup')
        ->set('description', 'Warm and comforting')
        ->set('servings', '4')
        ->set('prep_minutes', '10')
        ->set('cook_minutes', '20')
        ->set('difficulty', 'easy')
        ->set('cuisine', 'Italian')
        ->set('ingredients', [
            ['group' => '', 'quantity' => '2', 'unit' => 'cups', 'name' => 'Tomatoes', 'note' => ''],
            ['group' => '', 'quantity' => '1', 'unit' => '', 'name' => 'Onion', 'note' => 'diced'],
        ])
        ->set('steps', [
            ['instruction' => 'Chop the vegetables.', 'minutes' => '5'],
            ['instruction' => 'Simmer until soft.', 'minutes' => '15'],
        ])
        ->call('save');

    $recipe = Recipe::firstWhere('title', 'Tomato Soup');

    expect($recipe)->not->toBeNull()
        ->and($recipe->user_id)->toBe($user->id)
        ->and($recipe->total_minutes)->toBe(30)
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->steps)->toHaveCount(2);

    $component->assertRedirect(route('recipes.show', $recipe));
});

test('creating a recipe requires a title', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('recipes.manage')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors('title');
});

test('blank ingredient and step rows are skipped', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('recipes.manage')
        ->set('title', 'Sparse Recipe')
        ->set('ingredients', [
            ['group' => '', 'quantity' => '1', 'unit' => 'cup', 'name' => 'Flour', 'note' => ''],
            ['group' => '', 'quantity' => '', 'unit' => '', 'name' => '', 'note' => ''],
        ])
        ->set('steps', [
            ['instruction' => 'Mix.', 'minutes' => ''],
            ['instruction' => '', 'minutes' => ''],
        ])
        ->call('save');

    $recipe = Recipe::firstWhere('title', 'Sparse Recipe');

    expect($recipe->ingredients)->toHaveCount(1)
        ->and($recipe->steps)->toHaveCount(1);
});

test('a user can view their own recipe', function () {
    $recipe = Recipe::factory()->create(['title' => 'My Dish']);

    $this->actingAs($recipe->user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('My Dish');
});

test('a user cannot view another users recipe', function () {
    $recipe = Recipe::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();
});

test('a user cannot edit another users recipe', function () {
    $recipe = Recipe::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.edit', $recipe))
        ->assertForbidden();
});

test('a user can update their recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Old Title']);

    $this->actingAs($user);

    Volt::test('recipes.manage', ['recipe' => $recipe])
        ->set('title', 'New Title')
        ->call('save');

    expect($recipe->refresh()->title)->toBe('New Title');
});

test('a user can delete their recipe from the detail page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('delete')
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
});

test('a user cannot delete another users recipe from the list', function () {
    $recipe = Recipe::factory()->create();

    $this->actingAs(User::factory()->create());

    Volt::test('dashboard')
        ->call('delete', $recipe)
        ->assertForbidden();

    $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
});

test('the dashboard only lists the current users recipes', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['title' => 'Mine']);
    Recipe::factory()->create(['title' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});
