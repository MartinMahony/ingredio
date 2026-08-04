<?php

use function Livewire\Volt\{state, rules, mount, layout};
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

layout('layouts.auth');

state([
    'token' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
]);

rules([
    'token' => ['required'],
    'email' => ['required', 'string', 'email'],
    'password' => ['required', 'string', 'confirmed', 'min:8'],
]);

mount(function (string $token) {
    $this->token = $token;
    $this->email = request()->string('email')->toString();
});

$resetPassword = function () {
    $this->validate();

    $status = Password::reset(
        $this->only('email', 'password', 'password_confirmation', 'token'),
        function ($user) {
            $user->forceFill([
                'password' => Hash::make($this->password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    if ($status !== Password::PASSWORD_RESET) {
        $this->addError('email', __($status));

        return;
    }

    session()->flash('status', __($status));

    $this->redirect(route('login'), navigate: true);
};

?>

<div>
    <h1 class="mb-1 text-lg font-semibold">Reset password</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Choose a new password for your account.</p>

    <form wire:submit="resetPassword" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input wire:model="email" id="email" type="email" required autocomplete="email"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">New password</label>
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
            Reset password
        </button>
    </form>
</div>
