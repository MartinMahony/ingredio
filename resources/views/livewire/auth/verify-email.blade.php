<?php

use function Livewire\Volt\{layout};

layout('layouts.auth');

?>

<div>
    <h1 class="mb-1 text-lg font-semibold">Verify your email</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Thanks for signing up! Before getting started, please verify your email address by clicking the link we just sent you. If you didn't receive the email, we can send you another.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf

        <button type="submit"
            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
            Resend verification email
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf

        <button type="submit"
            class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
            Log out
        </button>
    </form>
</div>
