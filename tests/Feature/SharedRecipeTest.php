<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Volt\Volt;

test('a recipe is not shared by default', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->isShared())->toBeFalse()
        ->and($recipe->share_token)->toBeNull();
});

test('an unshared recipe has no public link', function () {
    $recipe = Recipe::factory()->create();

    $this->get('/shared/some-made-up-token')->assertNotFound();
});

test('a user can enable a public link from the recipe page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('enableSharing');

    $recipe->refresh();

    expect($recipe->isShared())->toBeTrue()
        ->and($recipe->share_token)->not->toBeNull()
        ->and($recipe->shared_at)->not->toBeNull();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee($recipe->title);
});

test('a user cannot reach another users recipe page to enable sharing', function () {
    $recipe = Recipe::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('recipes.show', $recipe))
        ->assertForbidden();

    expect($recipe->fresh()->isShared())->toBeFalse();
});

test('regenerating the link invalidates the old token', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->enableSharing();
    $oldToken = $recipe->share_token;

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('enableSharing');

    $recipe->refresh();

    expect($recipe->share_token)->not->toBe($oldToken);

    $this->get(route('recipes.shared', $oldToken))->assertNotFound();
    $this->get(route('recipes.shared', $recipe->share_token))->assertOk();
});

test('a user can disable a public link', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->enableSharing();
    $token = $recipe->share_token;

    $this->actingAs($user);

    Volt::test('recipes.show', ['recipe' => $recipe])
        ->call('disableSharing');

    expect($recipe->fresh()->isShared())->toBeFalse();

    $this->get(route('recipes.shared', $token))->assertNotFound();
});

test('the shared page does not require authentication', function () {
    $recipe = Recipe::factory()->create();
    $recipe->enableSharing();

    $this->get(route('recipes.shared', $recipe->share_token))->assertOk();
});

test('the shared page shows ingredients, steps, and tags but no app chrome', function () {
    $recipe = Recipe::factory()->create(['title' => 'Public Pancakes']);
    $recipe->ingredients()->create(['position' => 0, 'name' => 'Flour']);
    $recipe->steps()->create(['position' => 0, 'instruction' => 'Mix everything.']);
    $recipe->enableSharing();

    $response = $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Public Pancakes')
        ->assertSee('Flour')
        ->assertSee('Mix everything.')
        ->assertDontSee('Edit')
        ->assertDontSee('Delete')
        ->assertDontSee('Log out');

    $response->assertSee('noindex, nofollow', false);
});

test('the shared route is rate limited', function () {
    $recipe = Recipe::factory()->create();
    $recipe->enableSharing();

    foreach (range(1, 30) as $_) {
        $this->get(route('recipes.shared', $recipe->share_token))->assertOk();
    }

    $this->get(route('recipes.shared', $recipe->share_token))->assertStatus(429);
});
