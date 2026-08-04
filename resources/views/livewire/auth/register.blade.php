<?php

use function Livewire\Volt\{state, rules, layout};
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

layout('layouts.auth');

state([
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
    'password' => ['required', 'string', 'confirmed', 'min:8'],
]);

$register = function () {
    $validated = $this->validate();

    $validated['password'] = Hash::make($validated['password']);

    $user = User::create($validated);

    event(new Registered($user));

    Auth::login($user);

    $this->redirect(route('dashboard'), navigate: true);
};

?>

<div>
    <h1 class="mb-1 text-lg font-semibold">Create your account</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Start scanning and organising recipes.</p>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Name</label>
            <input wire:model="name" id="name" type="text" required autofocus autocomplete="name"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input wire:model="email" id="email" type="email" required autocomplete="email"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input wire:model="password" id="password" type="password" required autocomplete="new-password"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" required
                autocomplete="new-password"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
        </div>

        <button type="submit"
            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
            Create account
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="text-orange-600 hover:underline">Log in</a>
    </p>
</div>
