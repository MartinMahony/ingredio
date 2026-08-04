<?php

use function Livewire\Volt\{state, mount};
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

state([
    'name' => '',
    'email' => '',
    'current_password' => '',
    'password' => '',
    'password_confirmation' => '',
]);

mount(function () {
    $this->name = auth()->user()->name;
    $this->email = auth()->user()->email;
});

$updateProfile = function () {
    $user = auth()->user();

    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
    ]);

    $user->fill($validated);

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    $user->save();

    session()->flash('profile-status', 'Profile updated.');
};

$updatePassword = function () {
    $validated = $this->validate([
        'current_password' => ['required', 'string', 'current_password'],
        'password' => ['required', 'string', 'confirmed', 'min:8'],
    ]);

    auth()->user()->update([
        'password' => Hash::make($validated['password']),
    ]);

    $this->reset('current_password', 'password', 'password_confirmation');

    session()->flash('password-status', 'Password updated.');
};

?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Profile settings</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your account details.</p>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="mb-4 text-base font-medium">Account</h2>

        @if (session('profile-status'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ session('profile-status') }}
            </div>
        @endif

        <form wire:submit="updateProfile" class="max-w-md space-y-4">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                <input wire:model="name" id="name" type="text" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input wire:model="email" id="email" type="email" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Save
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="mb-4 text-base font-medium">Update password</h2>

        @if (session('password-status'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ session('password-status') }}
            </div>
        @endif

        <form wire:submit="updatePassword" class="max-w-md space-y-4">
            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium">Current password</label>
                <input wire:model="current_password" id="current_password" type="password" autocomplete="current-password"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">New password</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm new password</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password"
                    autocomplete="new-password"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            </div>

            <button type="submit"
                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Update password
            </button>
        </form>
    </section>
</div>
