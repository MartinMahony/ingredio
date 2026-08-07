<?php

use function Livewire\Volt\{state, computed};
use App\Models\Collection;
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;

state(['search' => '', 'cuisine' => '', 'tag' => '', 'sort' => ''])->url(except: '');
state(['selected' => [], 'bulkCollectionId' => '', 'previewRecipeId' => null]);

$recipes = computed(function () {
    return auth()->user()
        ->recipes()
        ->withCount(['ingredients', 'steps'])
        ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
            $query->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('ingredients', fn ($query) => $query->where('name', 'like', "%{$this->search}%"));
        }))
        ->when($this->cuisine !== '', fn ($query) => $query->where('cuisine', $this->cuisine))
        ->when($this->tag !== '', fn ($query) => $query->whereHas('tags', fn ($query) => $query->where('tags.id', $this->tag)))
        ->when(true, fn ($query) => match ($this->sort) {
            'oldest' => $query->oldest(),
            'title_asc' => $query->orderBy('title'),
            'favorite' => $query->orderBy('is_favorite', 'desc')->latest(),
            'recently_cooked' => $query->orderByRaw('CASE WHEN last_cooked_at IS NULL THEN 1 ELSE 0 END, last_cooked_at DESC'),
            default => $query->latest(),
        })
        ->get();
});

$cuisines = computed(function () {
    return auth()->user()->recipes()->whereNotNull('cuisine')->distinct()->orderBy('cuisine')->pluck('cuisine');
});

$tags = computed(function () {
    return auth()->user()->tags()->orderBy('name')->get();
});

$hasAnyRecipes = computed(fn () => auth()->user()->recipes()->exists());

$collections = computed(function () {
    return auth()->user()->collections()->orderBy('name')->get();
});

$previewRecipe = computed(function () {
    if ($this->previewRecipeId === null) {
        return null;
    }

    return auth()->user()->recipes()->with(['ingredients', 'steps', 'tags'])->find($this->previewRecipeId);
});

$clearFilters = function () {
    $this->search = '';
    $this->cuisine = '';
    $this->tag = '';
    $this->sort = '';
};

$delete = function (Recipe $recipe) {
    Gate::authorize('delete', $recipe);

    $recipe->delete();

    unset($this->recipes);
};

$selectAll = function () {
    $this->selected = $this->recipes->pluck('id')->map(fn ($id) => (string) $id)->all();
};

$deselectAll = function () {
    $this->selected = [];
    $this->bulkCollectionId = '';
};

$deleteSelected = function () {
    $recipes = auth()->user()->recipes()->whereIn('id', $this->selected)->get();

    foreach ($recipes as $recipe) {
        Gate::authorize('delete', $recipe);
        $recipe->delete();
    }

    $this->selected = [];
    unset($this->recipes);
};

$addSelectedToCollection = function () {
    if ($this->bulkCollectionId === '') {
        return;
    }

    $collection = Collection::findOrFail($this->bulkCollectionId);
    Gate::authorize('update', $collection);

    $recipeIds = auth()->user()->recipes()->whereIn('id', $this->selected)->pluck('id')->all();
    $collection->recipes()->syncWithoutDetaching($recipeIds);

    $this->selected = [];
    $this->bulkCollectionId = '';
};

$openPreview = function (Recipe $recipe) {
    Gate::authorize('view', $recipe);

    $this->previewRecipeId = $recipe->id;
};

$closePreview = function () {
    $this->previewRecipeId = null;
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

        <div class="flex items-center gap-2">
            <a href="{{ route('recipes.create') }}" wire:navigate
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                New recipe
            </a>
            <a href="{{ route('scans.create') }}" wire:navigate
                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Scan a recipe
            </a>
        </div>
    </div>

    @if (! $this->hasAnyRecipes)
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-orange-50 dark:bg-orange-900/30">
                <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
            <h2 class="text-base font-medium">No recipes yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Scan a screenshot, photo, or PDF to extract a recipe automatically, or add one manually.
            </p>
            <div class="mt-4 flex items-center gap-2">
                <a href="{{ route('scans.create') }}" wire:navigate
                    class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                    Scan a recipe
                </a>
                <a href="{{ route('recipes.create') }}" wire:navigate
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    Add manually
                </a>
            </div>
        </div>
    @else
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by title or ingredient&hellip;"
                aria-label="Search recipes"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:max-w-xs dark:border-gray-700 dark:bg-gray-900">

            <select wire:model.live="cuisine" aria-label="Filter by cuisine"
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
                <option value="">All cuisines</option>
                @foreach ($this->cuisines as $cuisineOption)
                    <option value="{{ $cuisineOption }}">{{ $cuisineOption }}</option>
                @endforeach
            </select>

            @if ($this->tags->isNotEmpty())
                <select wire:model.live="tag" aria-label="Filter by tag"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">All tags</option>
                    @foreach ($this->tags as $tagOption)
                        <option value="{{ $tagOption->id }}">{{ $tagOption->name }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="sort" aria-label="Sort recipes"
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
                <option value="">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="title_asc">Title A&ndash;Z</option>
                <option value="favorite">Favorites</option>
                <option value="recently_cooked">Recently cooked</option>
            </select>

            @if ($search !== '' || $cuisine !== '' || $tag !== '' || $sort !== '')
                <button type="button" wire:click="clearFilters"
                    class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                    Clear filters
                </button>
            @endif
        </div>

        @if (count($this->selected) > 0)
            <div
                class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ count($this->selected) }} {{ Str::plural('recipe', count($this->selected)) }} selected
                </span>

                @if (count($this->selected) < $this->recipes->count())
                    <button type="button" wire:click="selectAll" wire:loading.attr="disabled" wire:target="selectAll"
                        class="text-sm text-orange-600 hover:text-orange-700 disabled:opacity-75">
                        Select all {{ $this->recipes->count() }}
                    </button>
                @else
                    <button type="button" wire:click="deselectAll" wire:loading.attr="disabled" wire:target="deselectAll"
                        class="text-sm text-orange-600 hover:text-orange-700 disabled:opacity-75">
                        Clear selection
                    </button>
                @endif

                @if ($this->collections->isNotEmpty())
                    <select wire:model="bulkCollectionId" aria-label="Add selected recipes to collection"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Add to collection&hellip;</option>
                        @foreach ($this->collections as $collectionOption)
                            <option value="{{ $collectionOption->id }}">{{ $collectionOption->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addSelectedToCollection"
                        wire:loading.attr="disabled" wire:target="addSelectedToCollection"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:opacity-75 dark:border-gray-700 dark:hover:bg-gray-800">
                        <span wire:loading.remove wire:target="addSelectedToCollection">Add</span>
                        <span wire:loading wire:target="addSelectedToCollection">Adding&hellip;</span>
                    </button>
                @endif

                <button type="button" wire:click="deleteSelected" wire:confirm="Delete the selected recipes?"
                    wire:loading.attr="disabled" wire:target="deleteSelected"
                    class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-75 dark:border-red-900/50 dark:hover:bg-red-900/30">
                    <span wire:loading.remove wire:target="deleteSelected">Delete selected</span>
                    <span wire:loading wire:target="deleteSelected">Deleting&hellip;</span>
                </button>

                <button type="button" wire:click="deselectAll" wire:loading.attr="disabled" wire:target="deselectAll"
                    class="ml-auto text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                    Cancel
                </button>
            </div>
        @endif

        @if ($this->recipes->isEmpty())
            <div
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-medium">No recipes match your filters</h2>
                <button type="button" wire:click="clearFilters"
                    class="mt-4 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    Clear filters
                </button>
            </div>
        @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->recipes as $recipe)
                <div wire:key="recipe-{{ $recipe->id }}"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                    <input type="checkbox" wire:model="selected" value="{{ $recipe->id }}"
                        class="absolute left-3 top-3 z-10 h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-600"
                        aria-label="Select {{ $recipe->title }}">

                    <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="flex-1 pl-8">
                        <h2 class="flex items-start gap-2 pr-6 text-base font-semibold group-hover:text-orange-600">
                            @if ($recipe->is_favorite)
                                <svg class="mt-0.5 h-4 w-4 flex-none text-orange-500" viewBox="0 0 20 20" fill="currentColor"
                                    aria-label="Favorite">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endif
                            <span>{{ $recipe->title }}</span>
                        </h2>

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
                            @if ($recipe->last_cooked_at)
                                <span>Cooked {{ $recipe->last_cooked_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </a>

                    <div class="absolute right-3 top-3 flex items-center gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                        <button type="button" wire:click="openPreview({{ $recipe->id }})"
                            wire:loading.attr="disabled" wire:target="openPreview"
                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:opacity-100 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            title="Quick view" aria-label="Quick view {{ $recipe->title }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>

                        <button type="button" wire:click="delete({{ $recipe->id }})"
                            wire:confirm="Delete this recipe? This cannot be undone."
                            wire:loading.attr="disabled" wire:target="delete"
                            class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 focus:opacity-100 disabled:opacity-75 dark:hover:bg-red-900/30"
                            title="Delete recipe" aria-label="Delete {{ $recipe->title }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($this->previewRecipe)
            <div x-data @keydown.escape.window="$wire.closePreview()"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                wire:click.self="closePreview">
                <div class="max-h-full w-full max-w-2xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-xl font-semibold">{{ $this->previewRecipe->title }}</h3>
                        <button type="button" wire:click="closePreview"
                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            aria-label="Close preview">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if ($this->previewRecipe->description)
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">{{ $this->previewRecipe->description }}</p>
                    @endif

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Ingredients
                            </h4>
                            @if ($this->previewRecipe->ingredients->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">No ingredients listed.</p>
                            @else
                                <ul class="space-y-1 text-sm">
                                    @foreach ($this->previewRecipe->ingredients as $ingredient)
                                        <li>{{ trim($ingredient->quantity . ' ' . $ingredient->unit) }} {{ $ingredient->name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div>
                            <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Method
                            </h4>
                            @if ($this->previewRecipe->steps->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">No instructions listed.</p>
                            @else
                                <ol class="list-decimal space-y-1 pl-4 text-sm">
                                    @foreach ($this->previewRecipe->steps as $step)
                                        <li>{{ $step->instruction }}</li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <a href="{{ route('recipes.show', $this->previewRecipe) }}" wire:navigate
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                            View full recipe
                        </a>
                    </div>
                </div>
            </div>
        @endif
        @endif
    @endif
</div>
