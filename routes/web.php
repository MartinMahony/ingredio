<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/dashboard');

Route::middleware('throttle:30,1')->group(function () {
    Volt::route('shared/{token}', 'shared.recipe')->name('recipes.shared');
});

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')->name('login');
    Volt::route('register', 'auth.register')->name('register');
    Volt::route('forgot-password', 'auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('settings/profile', 'settings.profile')->name('profile');

    Volt::route('recipes/scan', 'scans.create')->name('scans.create');
    Volt::route('scans/{scan}', 'scans.show')->name('scans.show');

    Volt::route('recipes/create', 'recipes.manage')->name('recipes.create');
    Volt::route('recipes/{recipe}', 'recipes.show')->name('recipes.show');
    Volt::route('recipes/{recipe}/edit', 'recipes.manage')->name('recipes.edit');

    Volt::route('collections', 'collections.index')->name('collections.index');
    Volt::route('collections/{collection}', 'collections.show')->name('collections.show');

    Route::post('logout', function () {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
