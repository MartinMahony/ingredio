<?php

use function Livewire\Volt\{state, mount, computed};
use App\Models\Collection;
use App\Models\Recipe;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

state(['recipe' => null, 'selectedCollectionId' => '']);

mount(function (Recipe $recipe) {
    Gate::authorize('view', $recipe);

    $this->recipe = $recipe->load(['ingredients', 'steps', 'tags', 'collections']);
});

$isOwner = computed(fn () => auth()->id() === $this->recipe->user_id);

$isSaved = computed(fn () => ! $this->isOwner && auth()->user()->hasSaved($this->recipe));

$save = function () {
    Gate::authorize('view', $this->recipe);
    abort_if($this->recipe->user_id === auth()->id(), 403);

    auth()->user()->savedRecipes()->syncWithoutDetaching([$this->recipe->id]);
};

$unsave = function () {
    auth()->user()->savedRecipes()->detach($this->recipe->id);
};

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

$duplicate = function () {
    Gate::authorize('view', $this->recipe);
    Gate::authorize('create', Recipe::class);

    $copy = $this->recipe->replicate()->fill([
        'user_id' => auth()->id(),
        'title' => 'Copy of '.$this->recipe->title,
        'share_token' => null,
        'shared_at' => null,
        'is_public' => false,
        'published_at' => null,
        'is_favorite' => false,
        'last_cooked_at' => null,
    ]);
    $copy->save();

    foreach ($this->recipe->ingredients as $ingredient) {
        $copy->ingredients()->create($ingredient->only(['group', 'position', 'quantity', 'unit', 'name', 'note']));
    }

    foreach ($this->recipe->steps as $step) {
        $copy->steps()->create($step->only(['position', 'instruction', 'minutes']));
    }

    if ($this->recipe->tags->isNotEmpty()) {
        $copy->tags()->attach($this->recipe->tags->pluck('id'));
    }

    $this->redirect(route('recipes.edit', ['recipe' => $copy, 'saved' => 1]), navigate: true);
};

$toggleFavorite = function () {
    Gate::authorize('update', $this->recipe);

    $this->recipe->toggleFavorite();
    $this->recipe->refresh();
};

$markCooked = function () {
    Gate::authorize('update', $this->recipe);

    $this->recipe->markCooked();
    $this->recipe->refresh();
};

$togglePublic = function () {
    Gate::authorize('update', $this->recipe);

    if ($this->recipe->is_public) {
        $this->recipe->makePrivate();
        $this->recipe->refresh();

        return;
    }

    $key = 'publish-recipe:'.auth()->id();

    if (RateLimiter::tooManyAttempts($key, 20)) {
        $seconds = RateLimiter::availableIn($key);
        $this->addError('publish', "You've made a lot of recipes public recently. Try again in ".ceil($seconds / 60).' minute(s).');

        return;
    }

    RateLimiter::hit($key, 3600);

    $this->recipe->makePublic();
    $this->recipe->refresh();
};

?>

<div x-data="{ cooking: false, checked: [] }">
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
            <button type="button" x-on:click="cooking = !cooking"
                x-text="cooking ? 'Stop cooking' : 'Cook mode'"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                Cook mode
            </button>

            @if ($this->isOwner)
                <button type="button" wire:click="toggleFavorite" wire:loading.attr="disabled" wire:target="toggleFavorite"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="toggleFavorite">
                        {{ $recipe->is_favorite ? 'Unfavorite' : 'Favorite' }}
                    </span>
                    <span wire:loading wire:target="toggleFavorite">Saving&hellip;</span>
                </button>
                <button type="button" wire:click="markCooked" wire:loading.attr="disabled" wire:target="markCooked"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="markCooked">Mark cooked</span>
                    <span wire:loading wire:target="markCooked">Saving&hellip;</span>
                </button>
                <button type="button" wire:click="togglePublic" wire:loading.attr="disabled" wire:target="togglePublic"
                    @if (! $recipe->is_public) wire:confirm="Make this recipe public? It will be visible to every Ingredio user. Your personal notes stay private, and if this recipe was imported, its source will be credited." @endif
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="togglePublic">
                        {{ $recipe->is_public ? 'Make private' : 'Make public' }}
                    </span>
                    <span wire:loading wire:target="togglePublic">Saving&hellip;</span>
                </button>
                @error('publish')
                    <span class="text-sm text-red-600" role="alert">{{ $message }}</span>
                @enderror
                <a href="{{ route('recipes.edit', $recipe) }}" wire:navigate
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                    Edit
                </a>
            @endif

            @unless ($this->isOwner)
                @if ($this->isSaved)
                    <button type="button" wire:click="unsave" wire:loading.attr="disabled" wire:target="unsave"
                        class="rounded-md border border-orange-200 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50 disabled:opacity-75 dark:border-orange-900/50 dark:text-orange-300 dark:hover:bg-orange-900/30">
                        <span wire:loading.remove wire:target="unsave">Saved &check;</span>
                        <span wire:loading wire:target="unsave">Removing&hellip;</span>
                    </button>
                @else
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:opacity-75 dark:border-gray-700 dark:hover:bg-gray-800">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving&hellip;</span>
                    </button>
                @endif
            @endunless

            <button type="button" wire:click="duplicate"
                wire:loading.attr="disabled" wire:target="duplicate"
                wire:confirm="{{ $this->isOwner ? 'Duplicate this recipe?' : 'Copy this recipe to your own recipes?' }}"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <span wire:loading.remove wire:target="duplicate">{{ $this->isOwner ? 'Duplicate' : 'Copy to my recipes' }}</span>
                <span wire:loading wire:target="duplicate">Copying&hellip;</span>
            </button>

            @if ($this->isOwner)
                <button type="button" wire:click="delete"
                    wire:loading.attr="disabled" wire:target="delete"
                    wire:confirm="Delete this recipe? This cannot be undone."
                    class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/30">
                    <span wire:loading.remove wire:target="delete">Delete</span>
                    <span wire:loading wire:target="delete">Deleting&hellip;</span>
                </button>
            @endif
        </div>
    </div>

    @if ($recipe->is_public && ! $this->isOwner)
        <div class="mb-6 rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800 dark:border-orange-900/50 dark:bg-orange-900/20 dark:text-orange-300">
            This is a public recipe shared by another Ingredio user.
        </div>
    @endif

    <article class="rounded-xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900 print:border-0 print:p-0">
        <header class="mb-6 border-b border-gray-100 pb-6 dark:border-gray-800">
            <h1 class="text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>

            @if ($recipe->description)
                <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $recipe->description }}</p>
            @endif

            @if ($recipe->wasImported())
                <p class="mt-2 text-xs text-gray-400">
                    @if ($recipe->source_url)
                        Imported from <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener nofollow"
                            class="underline hover:text-gray-600 dark:hover:text-gray-300">{{ $recipe->source_url }}</a>
                    @else
                        Imported from a photo or PDF
                    @endif
                </p>
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
                        <dd class="font-medium">
                            <a href="{{ route('dashboard', ['cuisine' => $recipe->cuisine]) }}" wire:navigate
                                class="hover:text-orange-600 hover:underline">
                                {{ $recipe->cuisine }}
                            </a>
                        </dd>
                    </div>
                @endif
                @if ($recipe->last_cooked_at)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Last cooked</dt>
                        <dd class="font-medium">{{ $recipe->last_cooked_at->diffForHumans() }}</dd>
                    </div>
                @endif
            </dl>

            @if ($recipe->hasNutrition())
                <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    @if ($recipe->calories !== null)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Calories</dt>
                            <dd class="font-medium">{{ $recipe->calories }} kcal</dd>
                        </div>
                    @endif
                    @if ($recipe->protein_grams !== null)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Protein</dt>
                            <dd class="font-medium">{{ $recipe->protein_grams }} g</dd>
                        </div>
                    @endif
                    @if ($recipe->carbs_grams !== null)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Carbs</dt>
                            <dd class="font-medium">{{ $recipe->carbs_grams }} g</dd>
                        </div>
                    @endif
                    @if ($recipe->fat_grams !== null)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Fat</dt>
                            <dd class="font-medium">{{ $recipe->fat_grams }} g</dd>
                        </div>
                    @endif
                </dl>
                <p class="mt-1 text-xs text-gray-400">Nutrition values are per serving, as stated in the source.</p>
            @endif

            @if ($recipe->tags->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($recipe->tags as $tag)
                        <a href="{{ route('dashboard', ['tag' => $tag->id]) }}" wire:navigate
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($this->isOwner)
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
                                    wire:loading.attr="disabled" wire:target="removeFromCollection"
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
                                    wire:loading.attr="disabled" wire:target="addToCollection"
                                    class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span wire:loading.remove wire:target="addToCollection">Add</span>
                                    <span wire:loading wire:target="addToCollection">Adding&hellip;</span>
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
                                wire:loading.attr="disabled" wire:target="enableSharing"
                                class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <span wire:loading.remove wire:target="enableSharing">Regenerate</span>
                                <span wire:loading wire:target="enableSharing">Saving&hellip;</span>
                            </button>
                            <button type="button" wire:click="disableSharing" wire:confirm="Disable the public link? It will stop working immediately."
                                wire:loading.attr="disabled" wire:target="disableSharing"
                                class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/30">
                                <span wire:loading.remove wire:target="disableSharing">Disable</span>
                                <span wire:loading wire:target="disableSharing">Disabling&hellip;</span>
                            </button>
                        </div>
                    @else
                        <button type="button" wire:click="enableSharing"
                            wire:loading.attr="disabled" wire:target="enableSharing"
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <span wire:loading.remove wire:target="enableSharing">Enable public link</span>
                            <span wire:loading wire:target="enableSharing">Enabling&hellip;</span>
                        </button>
                    @endif
                </div>
            @endif
        </header>

        <div class="grid gap-8 md:grid-cols-3">
            <section class="md:col-span-1">
                <h2 class="mb-3 text-lg font-semibold">Ingredients</h2>
                @if ($recipe->ingredients->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No ingredients listed.</p>
                @else
                    @php($ingredientGroups = $recipe->ingredients->groupBy('group'))
                    <div class="space-y-4 text-sm">
                        @foreach ($ingredientGroups as $group => $items)
                            @if ($group)
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $group }}</h3>
                            @endif
                            <ul class="space-y-1.5">
                                @foreach ($items as $ingredient)
                                    <li class="flex items-start gap-3">
                                        <input type="checkbox" x-show="cooking" x-model="checked"
                                            value="ingredient-{{ $ingredient->id }}"
                                            class="mt-0.5 h-4 w-4 flex-none rounded border-gray-300 text-orange-600 focus:ring-orange-600"
                                            aria-label="Mark {{ $ingredient->name }} as checked">
                                        <span class="whitespace-nowrap font-medium"
                                            x-bind:class="{ 'line-through opacity-50': checked.includes('ingredient-{{ $ingredient->id }}') }">
                                            {{ trim($ingredient->quantity . ' ' . $ingredient->unit) }}
                                        </span>
                                        <span x-bind:class="{ 'line-through opacity-50': checked.includes('ingredient-{{ $ingredient->id }}') }">
                                            {{ $ingredient->name }}
                                            @if ($ingredient->note)
                                                <span class="text-gray-500 dark:text-gray-400">({{ $ingredient->note }})</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
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
                            <li class="flex items-start gap-3">
                                <input type="checkbox" x-show="cooking" x-model="checked" value="step-{{ $step->id }}"
                                    class="mt-1 h-4 w-4 flex-none rounded border-gray-300 text-orange-600 focus:ring-orange-600"
                                    aria-label="Mark step {{ $loop->iteration }} as checked">
                                <span
                                    class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-orange-100 text-sm font-medium text-orange-700 dark:bg-orange-900/40 dark:text-orange-300"
                                    x-bind:class="{ 'line-through opacity-50': checked.includes('step-{{ $step->id }}') }">
                                    {{ $loop->iteration }}
                                </span>
                                <div class="text-sm leading-relaxed"
                                    x-bind:class="{ 'line-through opacity-50': checked.includes('step-{{ $step->id }}') }">
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

        @if ($recipe->notes && $this->isOwner)
            <section class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
                <h2 class="mb-2 text-lg font-semibold">Notes</h2>
                <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $recipe->notes }}</p>
            </section>
        @endif
    </article>
</div>
