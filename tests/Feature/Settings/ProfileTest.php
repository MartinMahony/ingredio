<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('updateProfile');

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com');
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});
