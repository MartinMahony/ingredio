<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('users can authenticate', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('authenticated users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function () {
    $user = User::factory()->create();

    $component = Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password');

    foreach (range(1, 5) as $_) {
        $component->call('login')->assertHasErrors('email');
    }

    $component
        ->call('login')
        ->assertHasErrors(['email' => fn ($rules, $messages) => str_contains($messages[0] ?? '', 'Too many login attempts')]);
});
