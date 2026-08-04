<?php

namespace App\Extraction\Contracts;

use App\Extraction\Data\ExtractedRecipe;
use App\Extraction\Data\ScanSource;
use App\Extraction\Exceptions\RecipeExtractionException;

interface RecipeExtractor
{
    /**
     * Extract structured recipe data from an uploaded source.
     *
     * @throws RecipeExtractionException
     */
    public function extract(ScanSource $source): ExtractedRecipe;
}
