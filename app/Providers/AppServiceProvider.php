<?php

namespace App\Providers;

use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Drivers\GeminiRecipeExtractor;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
