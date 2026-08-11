<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:normalize-recipe-titles {--dry-run : List the changes without saving them}')]
#[Description('Re-case scanned recipe titles to Title Case (e.g. "THAI CURRY" -> "Thai Curry")')]
class NormalizeRecipeTitles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        Recipe::query()
            ->where('source_type', '!=', 'manual')
            ->whereNotNull('source_type')
            ->select(['id', 'title'])
            ->chunkById(200, function ($recipes) use ($dryRun, &$updated) {
                foreach ($recipes as $recipe) {
                    $normalized = Str::title(Str::squish($recipe->title));

                    if ($normalized === $recipe->title) {
                        continue;
                    }

                    $this->line("#{$recipe->id}: \"{$recipe->title}\" -> \"{$normalized}\"");

                    if (! $dryRun) {
                        $recipe->update(['title' => $normalized]);
                    }

                    $updated++;
                }
            });

        $this->info($dryRun
            ? "Would update {$updated} recipe title(s)."
            : "Updated {$updated} recipe title(s).");

        return self::SUCCESS;
    }
}
