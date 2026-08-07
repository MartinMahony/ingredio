<?php

namespace App\Jobs;

use App\Actions\StoreExtractedRecipe;
use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Data\ScanSource;
use App\Extraction\Support\UrlContentFetcher;
use App\Models\RecipeScan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessRecipeScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 10;

    public function __construct(public RecipeScan $scan) {}

    public function handle(RecipeExtractor $extractor, StoreExtractedRecipe $store, UrlContentFetcher $fetcher): void
    {
        $this->scan->markProcessing();

        $source = $this->scan->source_type === 'url'
            ? ScanSource::fromText($fetcher->fetch($this->scan->source_url))
            : $this->readSourceFile();

        $extracted = $extractor->extract($source);

        $recipe = $store($this->scan->user, $extracted, [
            'source_type' => $this->scan->source_type,
            'source_url' => $this->scan->source_url,
            'extracted_at' => now(),
        ]);

        $this->scan->markReady($recipe);

        $this->cleanupSource();
    }

    /**
     * @throws RuntimeException if the file read back doesn't match the size
     *                          recorded at upload time. On a multi-container
     *                          deployment (web + queue worker on separate
     *                          filesystems/containers sharing a mounted
     *                          volume), the worker can occasionally read the
     *                          file before the write from the web container
     *                          has fully synced. Throwing here lets the
     *                          job's normal retry/backoff pick it up again
     *                          a few seconds later, rather than silently
     *                          sending a truncated file to the extractor.
     */
    private function readSourceFile(): ScanSource
    {
        $disk = Storage::disk($this->scan->source_disk);
        $contents = $disk->get($this->scan->source_path);

        if ($this->scan->source_size !== null && strlen($contents) !== $this->scan->source_size) {
            throw new RuntimeException(sprintf(
                'Source file size mismatch for scan %d: expected %d bytes, read %d bytes.',
                $this->scan->id,
                $this->scan->source_size,
                strlen($contents),
            ));
        }

        $mimeType = $disk->mimeType($this->scan->source_path) ?: 'application/octet-stream';

        return ScanSource::fromContents($contents, $mimeType);
    }

    private function cleanupSource(): void
    {
        if ($this->scan->source_type === 'url') {
            return;
        }

        if (! config('scanning.keep_source')) {
            Storage::disk($this->scan->source_disk)->delete($this->scan->source_path);
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
