<?php

use function Livewire\Volt\{state, computed};
use App\Models\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

state(['name' => '', 'description' => '']);

$collections = computed(function () {
    return auth()->user()
        ->collections()
        ->withCount('recipes')
        ->orderBy('name')
        ->get();
});

$create = function () {
    Gate::authorize('create', Collection::class);

    $this->validate([
        'name' => ['required', 'string', 'max:100', Rule::unique('collections')->where('user_id', auth()->id())],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    auth()->user()->collections()->create([
        'name' => $this->name,
        'description' => $this->description ?: null,
    ]);

    $this->reset('name', 'description');
    unset($this->collections);
};

$delete = function (Collection $collection) {
    Gate::authorize('delete', $collection);

    $collection->delete();

    unset($this->collections);
};

?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Collections</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Group your recipes into custom collections.
        </p>
    </div>

    <form wire:submit="create"
        class="mb-8 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:flex-row sm:items-end dark:border-gray-800 dark:bg-gray-900">
        <div class="flex-1">
            <label for="name" class="mb-1 block text-sm font-medium">Name</label>
            <input wire:model="name" id="name" type="text" placeholder="e.g. Weeknight dinners"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex-1">
            <label for="description" class="mb-1 block text-sm font-medium">Description (optional)</label>
            <input wire:model="description" id="description" type="text"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <button type="submit"
            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
            New collection
        </button>
    </form>

    @if ($this->collections->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-medium">No collections yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Create a collection above to start grouping your recipes.
            </p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->collections as $collection)
                <div wire:key="collection-{{ $collection->id }}"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                    <a href="{{ route('collections.show', $collection) }}" wire:navigate class="flex-1">
                        <h2 class="pr-6 text-base font-semibold group-hover:text-orange-600">{{ $collection->name }}</h2>
                        @if ($collection->description)
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ $collection->description }}
                            </p>
                        @endif
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            {{ $collection->recipes_count }} {{ Str::plural('recipe', $collection->recipes_count) }}
                        </p>
                    </a>

                    <button type="button" wire:click="delete({{ $collection->id }})"
                        wire:confirm="Delete this collection? Recipes inside it will not be deleted."
                        class="absolute right-3 top-3 rounded-md p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/30"
                        title="Delete collection">
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
