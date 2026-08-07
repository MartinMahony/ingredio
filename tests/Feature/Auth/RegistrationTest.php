<?php

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('register:'.request()->ip());
});

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('new users can register', function () {
    Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'email_verified_at' => null,
    ]);
});

test('registration is rate limited after three attempts', function () {
    $component = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    foreach (range(1, 3) as $i) {
        $component
            ->set('email', "user{$i}@example.com")
            ->call('register')
            ->assertRedirect(route('verification.notice'));
    }

    $component
        ->set('email', 'limited@example.com')
        ->call('register')
        ->assertHasErrors(['email' => fn ($rules, $messages) => str_contains($messages[0] ?? '', 'Too many registration attempts')]);
});
