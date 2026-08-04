<?php

use function Livewire\Volt\{state, mount, computed};
use App\Enums\ScanStatus;
use App\Models\RecipeScan;
use Illuminate\Support\Facades\Gate;

state(['scan' => null]);

mount(function (RecipeScan $scan) {
    Gate::authorize('view', $scan);

    $this->scan = $scan;
});

$poll = function () {
    $this->scan->refresh();

    if ($this->scan->status === ScanStatus::Ready && $this->scan->recipe_id) {
        $this->redirect(route('recipes.edit', $this->scan->recipe_id), navigate: true);
    }
};

$status = computed(fn () => $this->scan->status);

?>

<div class="mx-auto max-w-xl" @if (! $this->status->isFinished()) wire:poll.2s="poll" @endif>
    @if ($this->status === App\Enums\ScanStatus::Failed)
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-red-200 bg-red-50 p-10 text-center dark:border-red-900/50 dark:bg-red-900/20">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h1 class="text-lg font-semibold">We couldn't read that recipe</h1>
            <p class="mt-1 max-w-sm text-sm text-gray-600 dark:text-gray-400">
                The extraction failed. Try a clearer screenshot or a different file.
            </p>
            @if ($scan->error)
                <p class="mt-3 max-w-sm text-xs text-red-500">{{ $scan->error }}</p>
            @endif
            <a href="{{ route('scans.create') }}" wire:navigate
                class="mt-5 rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Try another file
            </a>
        </div>
    @else
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900">
            <svg class="mb-4 h-10 w-10 animate-spin text-orange-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <h1 class="text-lg font-semibold">Reading your recipe&hellip;</h1>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                We're extracting the ingredients and steps. This usually takes a few seconds.
            </p>
            @if ($scan->original_filename)
                <p class="mt-4 text-xs text-gray-400">{{ $scan->original_filename }}</p>
            @endif
        </div>
    @endif
</div>
