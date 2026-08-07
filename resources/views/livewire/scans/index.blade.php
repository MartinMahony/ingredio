<?php

use function Livewire\Volt\{state, computed};
use App\Enums\ScanStatus;
use App\Models\RecipeScan;
use Illuminate\Support\Facades\Gate;

state(['deletingScanId' => null]);

$scans = computed(function () {
    return auth()->user()
        ->recipeScans()
        ->with('recipe')
        ->latest()
        ->get();
});

$delete = function (RecipeScan $scan) {
    Gate::authorize('delete', $scan);

    $scan->delete();

    unset($this->scans);
};

?>

<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('scans.create') }}" wire:navigate
                class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                &larr; Back to scan
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">Scan history</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Recent recipe scans, including successful and failed extractions.
            </p>
        </div>

        <a href="{{ route('scans.create') }}" wire:navigate
            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
            New scan
        </a>
    </div>

    @if ($this->scans->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-medium">No scans yet</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Start by scanning a recipe from a screenshot, photo, PDF, or URL.
            </p>
            <a href="{{ route('scans.create') }}" wire:navigate
                class="mt-4 rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Scan a recipe
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->scans as $scan)
                <div wire:key="scan-{{ $scan->id }}"
                    class="flex items-start justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $scan->status === ScanStatus::Ready,
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $scan->status === ScanStatus::Failed,
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' => ! $scan->status->isFinished(),
                                ])>
                                {{ $scan->status->label() }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $scan->created_at->diffForHumans() }}</span>
                        </div>

                        <p class="mt-2 text-sm font-medium">
                            @if ($scan->recipe)
                                <a href="{{ route('recipes.show', $scan->recipe) }}" wire:navigate
                                    class="hover:text-orange-600 hover:underline">
                                    {{ $scan->recipe->title }}
                                </a>
                            @elseif ($scan->original_filename)
                                {{ $scan->original_filename }}
                            @elseif ($scan->source_url)
                                <a href="{{ $scan->source_url }}" target="_blank" rel="noopener"
                                    class="break-all hover:text-orange-600 hover:underline">
                                    {{ $scan->source_url }}
                                </a>
                            @else
                                Scan #{{ $scan->id }}
                            @endif
                        </p>

                        @if ($scan->error)
                            <p class="mt-1 text-xs text-red-600">{{ $scan->error }}</p>
                        @endif

                        @if ($scan->status === ScanStatus::Ready && $scan->recipe)
                            <a href="{{ route('recipes.edit', $scan->recipe) }}" wire:navigate
                                class="mt-3 inline-block text-sm text-orange-600 hover:underline">
                                Edit recipe &rarr;
                            </a>
                        @elseif ($scan->status === ScanStatus::Failed)
                            <a href="{{ route('scans.create') }}" wire:navigate
                                class="mt-3 inline-block text-sm text-orange-600 hover:underline">
                                Try again &rarr;
                            </a>
                        @endif
                    </div>

                    <button type="button" wire:click="delete({{ $scan->id }})"
                        wire:confirm="Delete this scan from your history?"
                        wire:loading.attr="disabled" wire:target="delete"
                        class="ml-4 rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-75 dark:hover:bg-red-900/30"
                        title="Delete scan" aria-label="Delete scan {{ $scan->id }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
