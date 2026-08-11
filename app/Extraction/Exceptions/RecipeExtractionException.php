<?php

namespace App\Extraction\Exceptions;

use RuntimeException;

class RecipeExtractionException extends RuntimeException
{
    public bool $truncated = false;

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

    public static function responseTruncated(): self
    {
        $exception = new self('Recipe extraction was cut off before it finished (the recipe may be too long). Try again or use a shorter source.');
        $exception->truncated = true;

        return $exception;
    }

    public static function unsafeUrl(string $reason): self
    {
        return new self("The URL could not be fetched: {$reason}");
    }

    public static function urlFetchFailed(string $message): self
    {
        return new self("Fetching the URL failed: {$message}");
    }

    public static function emptyPageContent(): self
    {
        return new self('No readable recipe content was found at that URL.');
    }
}
