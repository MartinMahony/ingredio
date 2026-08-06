<?php

use function Livewire\Volt\{state, mount, computed};
use App\Models\Collection;
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;

state(['recipe' => null, 'selectedCollectionId' => '']);

mount(function (Recipe $recipe) {
    Gate::authorize('view', $recipe);

    $this->recipe = $recipe->load(['ingredients', 'steps', 'tags', 'collections']);
});

$delete = function () {
    Gate::authorize('delete', $this->recipe);

    $this->recipe->delete();

    $this->redirect(route('dashboard'), navigate: true);
};

$availableCollections = computed(function () {
    $inCollectionIds = $this->recipe->collections->pluck('id');

    return auth()->user()->collections()->whereNotIn('id', $inCollectionIds)->orderBy('name')->get();
});

$addToCollection = function () {
    if ($this->selectedCollectionId === '') {
        return;
    }

    $collection = Collection::findOrFail($this->selectedCollectionId);
    Gate::authorize('update', $collection);

    $collection->recipes()->syncWithoutDetaching([$this->recipe->id]);
    $this->recipe->load('collections');
    $this->selectedCollectionId = '';
};

$removeFromCollection = function (int $collectionId) {
    $collection = Collection::findOrFail($collectionId);
    Gate::authorize('update', $collection);

    $collection->recipes()->detach($this->recipe->id);
    $this->recipe->load('collections');
};

$enableSharing = function () {
    Gate::authorize('update', $this->recipe);

    $this->recipe->enableSharing();
};

$disableSharing = function () {
    Gate::authorize('update', $this->recipe);

    $this->recipe->disableSharing();
};

?>

<div>
    <div class="mb-6 flex items-center justify-between print:hidden">
        <a href="{{ route('dashboard') }}" wire:navigate
            class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; Back to recipes
        </a>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                Print
            </button>
            <a href="{{ route('recipes.edit', $recipe) }}" wire:navigate
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                Edit
            </a>
            <button type="button" wire:click="delete"
                wire:confirm="Delete this recipe? This cannot be undone."
                class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/30">
                Delete
            </button>
        </div>
    </div>

    <article class="rounded-xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900 print:border-0 print:p-0">
        <header class="mb-6 border-b border-gray-100 pb-6 dark:border-gray-800">
            <h1 class="text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>

            @if ($recipe->description)
                <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $recipe->description }}</p>
            @endif

            <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                @if ($recipe->servings)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Serves</dt>
                        <dd class="font-medium">{{ $recipe->servings }}</dd>
                    </div>
                @endif
                @if ($recipe->prep_minutes)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Prep</dt>
                        <dd class="font-medium">{{ $recipe->prep_minutes }} min</dd>
                    </div>
                @endif
                @if ($recipe->cook_minutes)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cook</dt>
                        <dd class="font-medium">{{ $recipe->cook_minutes }} min</dd>
                    </div>
                @endif
                @if ($recipe->difficulty)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Difficulty</dt>
                        <dd class="font-medium">{{ $recipe->difficulty->label() }}</dd>
                    </div>
                @endif
                @if ($recipe->cuisine)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cuisine</dt>
                        <dd class="font-medium">{{ $recipe->cuisine }}</dd>
                    </div>
                @endif
            </dl>

            @if ($recipe->tags->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($recipe->tags as $tag)
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 print:hidden">
                <h3 class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Collections
                </h3>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($recipe->collections as $collection)
                        <span
                            class="flex items-center gap-1 rounded-full bg-orange-50 py-1 pl-2.5 pr-1 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                            {{ $collection->name }}
                            <button type="button" wire:click="removeFromCollection({{ $collection->id }})"
                                class="rounded-full p-0.5 hover:bg-orange-100 dark:hover:bg-orange-900/60"
                                title="Remove" aria-label="Remove from {{ $collection->name }}">
                                &times;
                            </button>
                        </span>
                    @endforeach

                    @if ($this->availableCollections->isNotEmpty())
                        <form wire:submit="addToCollection" class="flex items-center gap-1">
                            <select wire:model="selectedCollectionId" aria-label="Add to collection"
                                class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
                                <option value="">Add to collection&hellip;</option>
                                @foreach ($this->availableCollections as $collection)
                                    <option value="{{ $collection->id }}">{{ $collection->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                Add
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-4 print:hidden">
                <h3 class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Public link
                </h3>
                @if ($recipe->isShared())
                    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                        <input type="text" readonly x-ref="shareUrl" aria-label="Public share link"
                            value="{{ route('recipes.shared', $recipe->share_token) }}" onclick="this.select()"
                            class="w-full max-w-md rounded-md border-gray-300 bg-gray-50 py-1 text-xs text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <button type="button"
                            x-on:click="
                                $refs.shareUrl.select();
                                if (window.isSecureContext && navigator.clipboard) {
                                    navigator.clipboard.writeText($refs.shareUrl.value);
                                } else {
                                    document.execCommand('copy');
                                }
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <span x-show="! copied">Copy link</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                        <button type="button" wire:click="enableSharing" wire:confirm="Regenerate the link? The old link will stop working."
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            Regenerate
                        </button>
                        <button type="button" wire:click="disableSharing" wire:confirm="Disable the public link? It will stop working immediately."
                            class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/30">
                            Disable
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="enableSharing"
                        class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                        Enable public link
                    </button>
                @endif
            </div>
        </header>

        <div class="grid gap-8 md:grid-cols-3">
            <section class="md:col-span-1">
                <h2 class="mb-3 text-lg font-semibold">Ingredients</h2>
                @if ($recipe->ingredients->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No ingredients listed.</p>
                @else
                    @php($ingredientGroups = $recipe->ingredients->groupBy('group'))
                    <div class="space-y-4">
                        @foreach ($ingredientGroups as $group => $items)
                            <div>
                                @if ($group)
                                    <h3 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $group }}</h3>
                                @endif
                                <ul class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
                                    @foreach ($items as $ingredient)
                                        <li class="col-span-2 grid grid-cols-subgrid">
                                            <span class="whitespace-nowrap font-medium">{{ trim($ingredient->quantity . ' ' . $ingredient->unit) }}</span>
                                            <span>
                                                {{ $ingredient->name }}
                                                @if ($ingredient->note)
                                                    <span class="text-gray-500 dark:text-gray-400">({{ $ingredient->note }})</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="md:col-span-2">
                <h2 class="mb-3 text-lg font-semibold">Method</h2>
                @if ($recipe->steps->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No instructions listed.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($recipe->steps as $step)
                            <li class="flex gap-3">
                                <span
                                    class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-orange-100 text-sm font-medium text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
                                    {{ $loop->iteration }}
                                </span>
                                <div class="text-sm leading-relaxed">
                                    {{ $step->instruction }}
                                    @if ($step->minutes)
                                        <span class="text-gray-500 dark:text-gray-400"> — {{ $step->minutes }} min</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        @if ($recipe->notes)
            <section class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
                <h2 class="mb-2 text-lg font-semibold">Notes</h2>
                <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $recipe->notes }}</p>
            </section>
        @endif
    </article>
</div>
