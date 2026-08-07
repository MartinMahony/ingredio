<?php

namespace App\Extraction\Support;

use App\Extraction\Exceptions\RecipeExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a user-supplied URL and returns cleaned, readable page text,
 * guarding against SSRF on the initial request and every redirect hop.
 */
class UrlContentFetcher
{
    public function __construct(
        private readonly int $timeout,
        private readonly int $connectTimeout,
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
        $safeIps = UrlSafetyValidator::ensureSafe($url);

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'curl' => [
                        CURLOPT_RESOLVE => $this->buildResolve($url, $safeIps),
                    ],
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            $this->logFetch($url, $startedAt, error: $e->getMessage());
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
            $this->logFetch($url, $startedAt, status: $response->status());
            throw RecipeExtractionException::urlFetchFailed('HTTP '.$response->status());
        }

        $contentLength = (int) $response->header('Content-Length');

        if ($contentLength > $this->maxBytes) {
            throw RecipeExtractionException::unsafeUrl('the page is too large.');
        }

        $body = $this->readBody($response);

        $this->logFetch($url, $startedAt, status: $response->status(), bytes: strlen($body));

        return $body;
    }

    /**
     * Pin the hostname to one of the already-validated public IPs so the
     * connection cannot be redirected by a later DNS rebinding attack.
     *
     * @param  array<int, string>  $safeIps
     * @return array<int, string>
     */
    private function buildResolve(string $url, array $safeIps): array
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip = $safeIps[0] ?? '';

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }

        $address = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? '['.$ip.']'
            : $ip;

        return ["{$host}:{$port}:{$address}"];
    }

    private function readBody(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $read = 0;

        while (! $stream->eof()) {
            $chunk = $stream->read(min(8192, $this->maxBytes + 1 - $read));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
            $read += strlen($chunk);

            if ($read > $this->maxBytes) {
                $stream->close();
                throw RecipeExtractionException::unsafeUrl('the page is too large.');
            }
        }

        $stream->close();

        return $buffer;
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

    private function logFetch(string $url, float $startedAt, ?int $status = null, ?int $bytes = null, ?string $error = null): void
    {
        Log::info('URL content fetched', [
            'url' => $url,
            'ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'bytes' => $bytes,
            'error' => $error,
        ]);
    }
}
