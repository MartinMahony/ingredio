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

test('the edit form shows a saved banner when arriving from a scan or duplicate', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('recipes.edit', ['recipe' => $recipe, 'saved' => 1]))
        ->assertOk()
        ->assertSee('Already saved to your recipes')
        ->assertSee('Back to recipe')
        ->assertDontSee('Cancel');
});

test('the edit form shows the normal cancel link when not arriving from a save redirect', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('recipes.edit', $recipe))
        ->assertOk()
        ->assertDontSee('Already saved to your recipes')
        ->assertSee('Cancel');
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

test('a user can duplicate their recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Pancakes']);
    $recipe->ingredients()->create(['position' => 0, 'name' => 'Flour']);
    $recipe->steps()->create(['position' => 0, 'instruction' => 'Mix']);
    $tag = $user->tags()->create(['name' => 'breakfast']);
    $recipe->tags()->attach($tag);

    $this->actingAs($user);

    $component = Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('duplicate');

    $copy = Recipe::latest('id')->first();

    expect($copy->id)->not->toBe($recipe->id)
        ->and($copy->title)->toBe('Copy of Pancakes')
        ->and($copy->ingredients)->toHaveCount(1)
        ->and($copy->steps)->toHaveCount(1)
        ->and($copy->tags->pluck('id')->all())->toBe([$tag->id]);

    $component->assertRedirect(route('recipes.edit', ['recipe' => $copy, 'saved' => 1]));
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

test('a user can favorite and unfavorite their recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('toggleFavorite');

    expect($recipe->fresh()->is_favorite)->toBeTrue();

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('toggleFavorite');

    expect($recipe->fresh()->is_favorite)->toBeFalse();
});

test('a user can mark their recipe as cooked', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('markCooked');

    expect($recipe->fresh()->last_cooked_at)->not->toBeNull();
});

test('a user cannot view or modify another users recipe', function () {
    $recipe = Recipe::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();
});

test('a user can make and unmake their recipe public', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])->call('togglePublic');

    expect($recipe->fresh())
        ->is_public->toBeTrue()
        ->published_at->not->toBeNull();

    Volt::test('recipes.show', ['recipe' => $recipe])->call('togglePublic');

    expect($recipe->fresh()->is_public)->toBeFalse();
});

test('a user cannot make another users public recipe private (or vice versa)', function () {
    $recipe = Recipe::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create());

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('togglePublic')
        ->assertForbidden();

    expect($recipe->fresh()->is_public)->toBeTrue();
});

test('any user can view a public recipe belonging to someone else', function () {
    $recipe = Recipe::factory()->create(['is_public' => true, 'title' => 'Public Pancakes']);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Public Pancakes')
        ->assertSee('This is a public recipe shared by another Ingredio user.');
});

test('a private recipe stays hidden from other users even if is_public was never set', function () {
    $recipe = Recipe::factory()->create(['is_public' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();
});

test('owner-only controls and notes are hidden from a non-owner viewing a public recipe', function () {
    $recipe = Recipe::factory()->create([
        'is_public' => true,
        'notes' => 'My secret family trick',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertDontSee('My secret family trick')
        ->assertDontSee('Make private')
        ->assertDontSee('Delete this recipe')
        ->assertSee('Copy to my recipes');
});

test('a non-owner copying a public recipe becomes the owner of the copy', function () {
    $author = User::factory()->create();
    $recipe = Recipe::factory()->for($author)->create(['is_public' => true, 'title' => 'Pancakes']);
    $recipe->ingredients()->create(['position' => 0, 'name' => 'Flour']);

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('recipes.show', ['recipe' => $recipe])->call('duplicate');

    $copy = Recipe::where('title', 'Copy of Pancakes')->sole();

    expect($copy->user_id)->toBe($viewer->id)
        ->and($copy->user_id)->not->toBe($author->id)
        ->and($copy->is_public)->toBeFalse()
        ->and($copy->ingredients)->toHaveCount(1);
});

test('a user cannot view (or copy) a private recipe belonging to someone else', function () {
    $recipe = Recipe::factory()->create(['is_public' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();

    $this->assertDatabaseCount('recipes', 1);
});

test('making recipes public is rate-limited', function () {
    $user = User::factory()->create();
    $recipes = Recipe::factory()->for($user)->count(21)->create();

    $this->actingAs($user);

    foreach ($recipes->take(20) as $recipe) {
        Volt::test('recipes.show', ['recipe' => $recipe])->call('togglePublic');
    }

    expect($recipes->take(20)->every(fn ($recipe) => $recipe->fresh()->is_public))->toBeTrue();

    $blocked = $recipes->last();

    Volt::test('recipes.show', ['recipe' => $blocked])
        ->call('togglePublic')
        ->assertHasErrors('publish');

    expect($blocked->fresh()->is_public)->toBeFalse();
});

test('an imported public recipe shows its source attribution', function () {
    $recipe = Recipe::factory()->create([
        'is_public' => true,
        'source_type' => 'url',
        'source_url' => 'https://example.test/recipe',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Imported from')
        ->assertSee('https://example.test/recipe');
});

test('recipe description and notes are limited to 10000 characters', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('recipes.manage')
        ->set('title', 'Too Long')
        ->set('description', str_repeat('a', 10001))
        ->set('notes', str_repeat('b', 10001))
        ->call('save')
        ->assertHasErrors(['description', 'notes']);
});

test('ingredient and step arrays are limited to 200 entries', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('recipes.manage')
        ->set('title', 'Too Many Rows')
        ->set('ingredients', array_fill(0, 201, ['group' => '', 'quantity' => '', 'unit' => '', 'name' => 'x', 'note' => '']))
        ->set('steps', array_fill(0, 201, ['instruction' => 'x', 'minutes' => '']))
        ->call('save')
        ->assertHasErrors(['ingredients', 'steps']);
});
