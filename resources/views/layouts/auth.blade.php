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
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ url('/') }}" wire:navigate
                class="mb-8 block text-center text-2xl font-semibold tracking-tight text-orange-600 dark:text-orange-500">
                Ingredio
            </a>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
