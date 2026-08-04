<?php

namespace App\Extraction\Exceptions;

use RuntimeException;

class RecipeExtractionException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self('The Gemini API key is not configured.');
    }

    public static function requestFailed(string $message): self
    {
        return new self("Recipe extraction request failed: {$message}");
    }

    public static function invalidPayload(string $message): self
    {
        return new self("Recipe extraction returned an invalid payload: {$message}");
    }
}
