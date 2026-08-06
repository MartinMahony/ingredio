<?php

use function Livewire\Volt\{state, mount};
use App\Models\Collection;
use Illuminate\Support\Facades\Gate;

state(['collection' => null]);

mount(function (Collection $collection) {
    Gate::authorize('view', $collection);

    $this->collection = $collection->load('recipes');
});

$removeRecipe = function (int $recipeId) {
    Gate::authorize('update', $this->collection);

    $this->collection->recipes()->detach($recipeId);

    $this->collection->load('recipes');
};

$delete = function () {
    Gate::authorize('delete', $this->collection);

    $this->collection->delete();

    $this->redirect(route('collections.index'), navigate: true);
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('collections.index') }}" wire:navigate
            class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; Back to collections
        </a>

        <button type="button" wire:click="delete" wire:confirm="Delete this collection? Recipes inside it will not be deleted."
            class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/30">
            Delete collection
        </button>
    </div>

    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $collection->name }}</h1>
        @if ($collection->description)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $collection->description }}</p>
        @endif
    </div>

    @if ($collection->recipes->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-medium">No recipes in this collection yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Open a recipe and add it to this collection.
            </p>
            <a href="{{ route('dashboard') }}" wire:navigate
                class="mt-4 rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Browse recipes
            </a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($collection->recipes as $recipe)
                <div wire:key="recipe-{{ $recipe->id }}"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                    <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="flex-1">
                        <h2 class="pr-6 text-base font-semibold group-hover:text-orange-600">{{ $recipe->title }}</h2>
                        @if ($recipe->cuisine)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $recipe->cuisine }}</p>
                        @endif
                    </a>

                    <button type="button" wire:click="removeRecipe({{ $recipe->id }})"
                        wire:confirm="Remove this recipe from the collection?"
                        class="absolute right-3 top-3 rounded-md p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/30"
                        title="Remove from collection" aria-label="Remove {{ $recipe->title }} from this collection">
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
