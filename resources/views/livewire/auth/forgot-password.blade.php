<?php

use function Livewire\Volt\{state, rules, layout};
use Illuminate\Support\Facades\Password;

layout('layouts.auth');

state(['email' => '']);

rules(['email' => ['required', 'string', 'email']]);

$sendResetLink = function () {
    $this->validate();

    $status = Password::sendResetLink(['email' => $this->email]);

    if ($status !== Password::RESET_LINK_SENT) {
        $this->addError('email', __($status));

        return;
    }

    session()->flash('status', __($status));
    $this->reset('email');
};

?>

<div>
    <h1 class="mb-1 text-lg font-semibold">Forgot password</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Enter your email and we'll send you a reset link.
    </p>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input wire:model="email" id="email" type="email" required autofocus autocomplete="email"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
            Email password reset link
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('login') }}" wire:navigate class="text-orange-600 hover:underline">Back to log in</a>
    </p>
</div>
