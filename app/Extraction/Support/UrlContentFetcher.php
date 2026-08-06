<?php

namespace App\Extraction\Support;

use App\Extraction\Exceptions\RecipeExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches a user-supplied URL and returns cleaned, readable page text,
 * guarding against SSRF on the initial request and every redirect hop.
 */
class UrlContentFetcher
{
    public function __construct(
        private readonly int $timeout,
        private readonly int $maxBytes,
        private readonly int $maxRedirects,
        private readonly string $userAgent,
    ) {}

    /**
     * @throws RecipeExtractionException
     */
    public function fetch(string $url): string
    {
        $html = $this->fetchHtml($url, $this->maxRedirects);
        $text = HtmlTextExtractor::extract($html);

        if (trim($text) === '') {
            throw RecipeExtractionException::emptyPageContent();
        }

        return $text;
    }

    private function fetchHtml(string $url, int $redirectsRemaining): string
    {
        UrlSafetyValidator::ensureSafe($url);

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout($this->timeout)
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException $e) {
            throw RecipeExtractionException::urlFetchFailed($e->getMessage());
        }

        if ($response->redirect()) {
            $location = $response->header('Location');

            if (! $location || $redirectsRemaining <= 0) {
                throw RecipeExtractionException::unsafeUrl('too many redirects.');
            }

            return $this->fetchHtml((string) $this->resolveRedirect($url, $location), $redirectsRemaining - 1);
        }

        if ($response->failed()) {
            throw RecipeExtractionException::urlFetchFailed('HTTP '.$response->status());
        }

        $contentLength = (int) $response->header('Content-Length');

        if ($contentLength > $this->maxBytes) {
            throw RecipeExtractionException::unsafeUrl('the page is too large.');
        }

        $body = $response->body();

        if (strlen($body) > $this->maxBytes) {
            throw RecipeExtractionException::unsafeUrl('the page is too large.');
        }

        return $body;
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        $baseParts = parse_url($base);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        $path = str_starts_with($location, '/')
            ? $location
            : rtrim(dirname($baseParts['path'] ?? '/'), '/').'/'.$location;

        return "{$scheme}://{$host}{$port}{$path}";
    }
}
