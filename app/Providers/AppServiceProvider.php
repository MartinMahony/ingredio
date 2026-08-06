<?php

namespace App\Providers;

use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Drivers\GeminiRecipeExtractor;
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
                ),
            };
        });

        $this->app->singleton(UrlContentFetcher::class, function (): UrlContentFetcher {
            return new UrlContentFetcher(
                timeout: (int) config('scanning.url.timeout'),
                maxBytes: (int) config('scanning.url.max_bytes'),
                maxRedirects: (int) config('scanning.url.max_redirects'),
                userAgent: (string) config('scanning.url.user_agent'),
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
