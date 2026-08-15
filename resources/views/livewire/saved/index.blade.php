<?php

use function Livewire\Volt\{computed};

$recipes = computed(function () {
    return auth()->user()
        ->savedRecipes()
        ->where('is_public', true)
        ->with('user')
        ->withCount(['ingredients', 'steps'])
        ->get();
});

$unsave = function (int $recipeId) {
    auth()->user()->savedRecipes()->detach($recipeId);
    unset($this->recipes);
};

?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Saved recipes</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Recipes you've bookmarked from other Ingredio users.
        </p>
    </div>

    @if ($this->recipes->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-medium">Nothing saved yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Browse <a href="{{ route('discover.index') }}" wire:navigate class="text-orange-600 hover:underline">Discover</a>
                and save recipes you'd like to come back to.
            </p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->recipes as $recipe)
                <div wire:key="saved-recipe-{{ $recipe->id }}"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                    <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="flex-1">
                        <h2 class="text-base font-semibold group-hover:text-orange-600">
                            <span class="truncate">{{ $recipe->title }}</span>
                        </h2>

                        <p class="mt-1 text-xs text-gray-400">by {{ $recipe->user->name }}</p>

                        @if ($recipe->description)
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ $recipe->description }}
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($recipe->cuisine)
                                <span>{{ $recipe->cuisine }}</span>
                            @endif
                            @if ($recipe->difficulty)
                                <span>{{ $recipe->difficulty->label() }}</span>
                            @endif
                            @if ($recipe->total_minutes)
                                <span>{{ $recipe->total_minutes }} min</span>
                            @endif
                            <span>{{ $recipe->ingredients_count }} {{ Str::plural('ingredient', $recipe->ingredients_count) }}</span>
                            <span>{{ $recipe->steps_count }} {{ Str::plural('step', $recipe->steps_count) }}</span>
                        </div>
                    </a>

                    <button type="button" wire:click="unsave({{ $recipe->id }})"
                        wire:confirm="Remove this recipe from your saved list?"
                        wire:loading.attr="disabled" wire:target="unsave"
                        class="absolute right-3 top-3 rounded-md p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100 disabled:opacity-75 dark:hover:bg-red-900/30"
                        title="Remove from saved" aria-label="Remove {{ $recipe->title }} from saved">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
