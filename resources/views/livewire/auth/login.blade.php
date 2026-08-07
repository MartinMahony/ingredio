<?php

use function Livewire\Volt\{state, rules, layout};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

layout('layouts.auth');

state([
    'email' => '',
    'password' => '',
    'remember' => false,
]);

rules([
    'email' => ['required', 'string', 'email'],
    'password' => ['required', 'string'],
]);

$login = function () {
    $this->validate();

    $key = 'login:'.strtolower($this->email).':'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Please try again in '.$seconds.' seconds.',
        ]);
    }

    if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        RateLimiter::hit($key, 60);

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    RateLimiter::clear($key);

    session()->regenerate();

    $this->redirectIntended(route('dashboard'), navigate: true);
};

?>

<div>
    <h1 class="mb-1 text-lg font-semibold">Welcome back</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Log in to your Ingredio account.</p>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input wire:model="email" id="email" type="email" required autofocus autocomplete="email"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input wire:model="password" id="password" type="password" required autocomplete="current-password"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input wire:model="remember" type="checkbox"
                    class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" wire:navigate
                class="text-sm text-orange-600 hover:underline">Forgot password?</a>
        </div>

        <button type="submit"
            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
            Log in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Don't have an account?
        <a href="{{ route('register') }}" wire:navigate class="text-orange-600 hover:underline">Sign up</a>
    </p>
</div>
