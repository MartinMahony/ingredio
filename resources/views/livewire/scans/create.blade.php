<?php

use function Livewire\Volt\{state, usesFileUploads};
use App\Jobs\ProcessRecipeScan;
use App\Models\RecipeScan;
use Illuminate\Support\Facades\Gate;

usesFileUploads();

state(['file' => null]);

$scan = function () {
    Gate::authorize('create', RecipeScan::class);

    $maxKb = (int) config('scanning.max_upload_kb');
    $mimes = implode(',', config('scanning.allowed_mimes'));

    $this->validate([
        'file' => ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
    ], [
        'file.mimes' => 'Upload a JPG, PNG, WebP, or PDF file.',
        'file.max' => 'The file may not be larger than :max KB.',
    ]);

    $disk = config('scanning.disk');
    $path = $this->file->store('scans', $disk);

    $isPdf = $this->file->getClientOriginalExtension() === 'pdf'
        || $this->file->getMimeType() === 'application/pdf';

    $scan = RecipeScan::create([
        'user_id' => auth()->id(),
        'status' => 'pending',
        'source_type' => $isPdf ? 'pdf' : 'image',
        'source_disk' => $disk,
        'source_path' => $path,
        'original_filename' => $this->file->getClientOriginalName(),
        'provider' => config('scanning.driver'),
        'model' => config('scanning.model'),
    ]);

    ProcessRecipeScan::dispatch($scan);

    $this->redirect(route('scans.show', $scan), navigate: true);
};

?>

<div class="mx-auto max-w-xl">
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" wire:navigate
            class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; Back to recipes
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Scan a recipe</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Upload a screenshot, photo, or PDF and we'll extract the recipe for you to review.
        </p>
    </div>

    <form wire:submit="scan" class="space-y-4">
        <label
            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white p-10 text-center transition hover:border-orange-400 dark:border-gray-700 dark:bg-gray-900">
            <input type="file" wire:model="file" accept="image/*,application/pdf" capture="environment"
                class="sr-only" />

            <svg class="mb-3 h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>

            <div wire:loading.remove wire:target="file">
                @if ($file)
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $file->getClientOriginalName() }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tap to choose a different file</p>
                @else
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Tap to upload or take a photo</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG, WebP, or PDF</p>
                @endif
            </div>

            <div wire:loading wire:target="file" class="text-sm text-gray-500 dark:text-gray-400">
                Uploading&hellip;
            </div>
        </label>

        @error('file')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="file,scan"
            @disabled(! $file)
            class="w-full rounded-md bg-orange-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50">
            <span wire:loading.remove wire:target="scan">Extract recipe</span>
            <span wire:loading wire:target="scan">Starting&hellip;</span>
        </button>
    </form>
</div>
