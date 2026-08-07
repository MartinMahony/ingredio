<?php

namespace App\Providers;

use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Drivers\GeminiRecipeExtractor;
use App\Extraction\Support\ScanRateLimiter;
use App\Extraction\Support\UrlContentFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RecipeExtractor::class, function (): RecipeExtractor {
            return match (config('scanning.driver')) {
                default => new GeminiRecipeExtractor(
                    apiKey: (string) config('services.gemini.key'),
                    model: (string) config('scanning.model'),
                    baseUrl: (string) config('scanning.gemini.base_url'),
                    timeout: (int) config('scanning.gemini.timeout'),
                    maxOutputTokens: (int) config('scanning.gemini.max_output_tokens'),
                ),
            };
        });

        $this->app->singleton(UrlContentFetcher::class, function (): UrlContentFetcher {
            return new UrlContentFetcher(
                timeout: (int) config('scanning.url.timeout'),
                connectTimeout: (int) config('scanning.url.connect_timeout'),
                maxBytes: (int) config('scanning.url.max_bytes'),
                maxRedirects: (int) config('scanning.url.max_redirects'),
                userAgent: (string) config('scanning.url.user_agent'),
            );
        });

        $this->app->singleton(ScanRateLimiter::class, function (): ScanRateLimiter {
            return new ScanRateLimiter(
                perMinute: (int) config('scanning.rate_limit.per_minute'),
                perDay: (int) config('scanning.rate_limit.per_day'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
