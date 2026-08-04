<?php

use function Livewire\Volt\{computed};
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;

$recipes = computed(function () {
    return auth()->user()
        ->recipes()
        ->withCount(['ingredients', 'steps'])
        ->latest()
        ->get();
});

$delete = function (Recipe $recipe) {
    Gate::authorize('delete', $recipe);

    $recipe->delete();

    unset($this->recipes);
};

?>

<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">My Recipes</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $this->recipes->count() }} {{ Str::plural('recipe', $this->recipes->count()) }} in your library.
            </p>
        </div>

        <a href="{{ route('recipes.create') }}" wire:navigate
            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
            New recipe
        </a>
    </div>

    @if ($this->recipes->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-orange-50 dark:bg-orange-900/30">
                <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
            <h2 class="text-base font-medium">No recipes yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Add a recipe manually to get started. Scanning from screenshots and PDFs is coming soon.
            </p>
            <a href="{{ route('recipes.create') }}" wire:navigate
                class="mt-4 rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Add your first recipe
            </a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->recipes as $recipe)
                <div wire:key="recipe-{{ $recipe->id }}"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                    <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="flex-1">
                        <h2 class="pr-6 text-base font-semibold group-hover:text-orange-600">{{ $recipe->title }}</h2>

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
                            <span>{{ $recipe->ingredients_count }} ingredients</span>
                        </div>
                    </a>

                    <button type="button" wire:click="delete({{ $recipe->id }})"
                        wire:confirm="Delete this recipe? This cannot be undone."
                        class="absolute right-3 top-3 rounded-md p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/30"
                        title="Delete recipe">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
