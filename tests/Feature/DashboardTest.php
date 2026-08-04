<?php

use App\Models\User;

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
