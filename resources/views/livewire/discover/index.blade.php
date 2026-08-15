<?php

use function Livewire\Volt\{state, computed, usesPagination};
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;

usesPagination();

state(['search' => '', 'cuisine' => '', 'sort' => ''])->url(except: '');

$recipes = computed(function () {
    return Recipe::query()
        ->where('is_public', true)
        ->where('user_id', '!=', auth()->id())
        ->with('user')
        ->withCount(['ingredients', 'steps'])
        ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
            $query->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('ingredients', fn ($query) => $query->where('name', 'like', "%{$this->search}%"));
        }))
        ->when($this->cuisine !== '', fn ($query) => $query->where('cuisine', $this->cuisine))
        ->when(true, fn ($query) => match ($this->sort) {
            'oldest' => $query->oldest('published_at'),
            'title_asc' => $query->orderBy('title'),
            default => $query->latest('published_at'),
        })
        ->paginate(12);
});

$cuisines = computed(function () {
    return Recipe::query()
        ->where('is_public', true)
        ->where('user_id', '!=', auth()->id())
        ->whereNotNull('cuisine')
        ->distinct()
        ->orderBy('cuisine')
        ->pluck('cuisine');
});

$clearFilters = function () {
    $this->search = '';
    $this->cuisine = '';
    $this->sort = '';
    $this->resetPage();
};

$savedRecipeIds = computed(fn () => auth()->user()->savedRecipes()->pluck('recipes.id')->all());

$save = function (Recipe $recipe) {
    Gate::authorize('view', $recipe);
    abort_if($recipe->user_id === auth()->id(), 403);

    auth()->user()->savedRecipes()->syncWithoutDetaching([$recipe->id]);
    unset($this->savedRecipeIds);
};

$unsave = function (Recipe $recipe) {
    auth()->user()->savedRecipes()->detach($recipe->id);
    unset($this->savedRecipeIds);
};

?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Discover</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Public recipes shared by other Ingredio users.
        </p>
    </div>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by title or ingredient&hellip;"
            aria-label="Search public recipes"
            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:max-w-xs dark:border-gray-700 dark:bg-gray-900">

        <select wire:model.live="cuisine" aria-label="Filter by cuisine"
            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
            <option value="">All cuisines</option>
            @foreach ($this->cuisines as $cuisineOption)
                <option value="{{ $cuisineOption }}">{{ $cuisineOption }}</option>
            @endforeach
        </select>

        <select wire:model.live="sort" aria-label="Sort recipes"
            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
            <option value="">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="title_asc">Title A&ndash;Z</option>
        </select>

        @if ($search !== '' || $cuisine !== '' || $sort !== '')
            <button type="button" wire:click="clearFilters"
                class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                Clear filters
            </button>
        @endif
    </div>

    @if ($this->recipes->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-medium">No public recipes {{ $search !== '' || $cuisine !== '' ? 'match your filters' : 'yet' }}</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                When other Ingredio users make a recipe public, it'll show up here.
            </p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->recipes as $recipe)
                <div wire:key="discover-recipe-{{ $recipe->id }}"
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

                    @if (in_array($recipe->id, $this->savedRecipeIds))
                        <button type="button" wire:click="unsave({{ $recipe->id }})"
                            wire:loading.attr="disabled" wire:target="unsave"
                            class="mt-3 rounded-md border border-orange-200 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50 disabled:opacity-75 dark:border-orange-900/50 dark:text-orange-300 dark:hover:bg-orange-900/30">
                            <span wire:loading.remove wire:target="unsave">Saved &check;</span>
                            <span wire:loading wire:target="unsave">Removing&hellip;</span>
                        </button>
                    @else
                        <button type="button" wire:click="save({{ $recipe->id }})"
                            wire:loading.attr="disabled" wire:target="save"
                            class="mt-3 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:opacity-75 dark:border-gray-700 dark:hover:bg-gray-800">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving&hellip;</span>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->recipes->onEachSide(1)->links() }}
        </div>
    @endif
</div>
