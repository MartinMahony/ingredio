<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <title>{{ $title ?? config('app.name', 'Ingredio') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="min-h-full">
        <nav class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="text-lg font-semibold tracking-tight text-orange-600 dark:text-orange-500">
                    Ingredio
                </a>

                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        Recipes
                    </a>
                    <a href="{{ route('collections.index') }}" wire:navigate
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        Collections
                    </a>
                    <a href="{{ route('profile') }}" wire:navigate
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        {{ auth()->user()?->name }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-md px-2 py-1 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</body>

</html>
