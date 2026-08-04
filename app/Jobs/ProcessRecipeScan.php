<?php

namespace App\Jobs;

use App\Actions\StoreExtractedRecipe;
use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Data\ScanSource;
use App\Models\RecipeScan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessRecipeScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public RecipeScan $scan) {}

    public function handle(RecipeExtractor $extractor, StoreExtractedRecipe $store): void
    {
        $this->scan->markProcessing();

        $disk = Storage::disk($this->scan->source_disk);
        $contents = $disk->get($this->scan->source_path);
        $mimeType = $disk->mimeType($this->scan->source_path) ?: 'application/octet-stream';

        $source = ScanSource::fromContents($contents, $mimeType);

        $extracted = $extractor->extract($source);

        $recipe = $store($this->scan->user, $extracted, [
            'source_type' => $this->scan->source_type,
            'extracted_at' => now(),
        ]);

        $this->scan->markReady($recipe);

        if (! config('scanning.keep_source')) {
            $disk->delete($this->scan->source_path);
            $this->scan->update(['source_kept' => false]);
        } else {
            $this->scan->update(['source_kept' => true]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->scan->markFailed($exception?->getMessage() ?? 'Unknown error');
    }
}
