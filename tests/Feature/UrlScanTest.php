<?php

use App\Actions\StoreExtractedRecipe;
use App\Enums\ScanStatus;
use App\Extraction\Contracts\RecipeExtractor;
use App\Extraction\Exceptions\RecipeExtractionException;
use App\Extraction\Support\HtmlTextExtractor;
use App\Extraction\Support\UrlContentFetcher;
use App\Extraction\Support\UrlSafetyValidator;
use App\Jobs\ProcessRecipeScan;
use App\Models\Recipe;
use App\Models\RecipeScan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

/**
 * @param  array<string, mixed>  $recipe
 * @return array<string, mixed>
 */
function fakeUrlGeminiResponse(array $recipe): array
{
    return [
        'candidates' => [[
            'content' => [
                'parts' => [[
                    'text' => json_encode($recipe),
                ]],
            ],
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function sampleUrlRecipePayload(): array
{
    return [
        'title' => 'Web Pancakes',
        'ingredients' => [
            ['name' => 'flour', 'quantity' => '2', 'unit' => 'cups'],
        ],
        'steps' => [
            ['instruction' => 'Mix and cook.'],
        ],
        'tags' => [],
    ];
}

function urlFetcher(): UrlContentFetcher
{
    return new UrlContentFetcher(timeout: 5, maxBytes: 1_000_000, maxRedirects: 3, userAgent: 'TestBot/1.0');
}

describe('UrlSafetyValidator', function () {
    it('rejects unsupported schemes', function () {
        UrlSafetyValidator::ensureSafe('ftp://1.1.1.1/recipe');
    })->throws(RecipeExtractionException::class);

    it('rejects malformed urls', function () {
        UrlSafetyValidator::ensureSafe('not a url');
    })->throws(RecipeExtractionException::class);

    it('rejects loopback addresses', function () {
        UrlSafetyValidator::ensureSafe('http://127.0.0.1/recipe');
    })->throws(RecipeExtractionException::class);

    it('rejects private network addresses', function () {
        UrlSafetyValidator::ensureSafe('http://192.168.1.5/recipe');
    })->throws(RecipeExtractionException::class);

    it('rejects the cloud metadata address', function () {
        UrlSafetyValidator::ensureSafe('http://169.254.169.254/latest/meta-data');
    })->throws(RecipeExtractionException::class);

    it('allows public ip literals', function () {
        UrlSafetyValidator::ensureSafe('http://1.1.1.1/recipe');
    })->throwsNoExceptions();
});

describe('HtmlTextExtractor', function () {
    it('strips scripts, styles, and nav chrome', function () {
        $html = <<<'HTML'
        <html>
            <head><style>body{color:red}</style></head>
            <body>
                <nav>Home | About</nav>
                <script>alert('hi')</script>
                <main><h1>Pancakes</h1><p>Mix flour and eggs.</p></main>
                <footer>Copyright</footer>
            </body>
        </html>
        HTML;

        $text = HtmlTextExtractor::extract($html);

        expect($text)->toContain('Pancakes')
            ->toContain('Mix flour and eggs.')
            ->not->toContain('alert')
            ->not->toContain('Home | About')
            ->not->toContain('Copyright');
    });

    it('returns an empty string for empty input', function () {
        expect(HtmlTextExtractor::extract(''))->toBe('');
    });
});

describe('UrlContentFetcher', function () {
    it('fetches and cleans page content', function () {
        Http::fake([
            '*' => Http::response('<html><body><h1>Soup</h1><p>Simmer tomatoes.</p></body></html>'),
        ]);

        $text = urlFetcher()->fetch('http://1.1.1.1/recipe');

        expect($text)->toContain('Soup')->toContain('Simmer tomatoes.');
    });

    it('follows a redirect to another safe host', function () {
        Http::fake([
            'http://1.1.1.1/*' => Http::response('', 302, ['Location' => 'http://1.0.0.1/final']),
            'http://1.0.0.1/*' => Http::response('<html><body>Final recipe</body></html>'),
        ]);

        $text = urlFetcher()->fetch('http://1.1.1.1/recipe');

        expect($text)->toContain('Final recipe');
    });

    it('refuses to follow a redirect into a private network', function () {
        Http::fake([
            '*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
        ]);

        urlFetcher()->fetch('http://1.1.1.1/recipe');
    })->throws(RecipeExtractionException::class);

    it('rejects responses larger than the configured limit', function () {
        Http::fake([
            '*' => Http::response('x', 200, ['Content-Length' => '2000000']),
        ]);

        urlFetcher()->fetch('http://1.1.1.1/recipe');
    })->throws(RecipeExtractionException::class);

    it('rejects pages with no readable text', function () {
        Http::fake([
            '*' => Http::response('<html><head><script>1</script></head><body></body></html>'),
        ]);

        urlFetcher()->fetch('http://1.1.1.1/recipe');
    })->throws(RecipeExtractionException::class);

    it('throws when the request fails', function () {
        Http::fake([
            '*' => Http::response('not found', 404),
        ]);

        urlFetcher()->fetch('http://1.1.1.1/recipe');
    })->throws(RecipeExtractionException::class);
});

describe('ProcessRecipeScan for url sources', function () {
    it('fetches, extracts, and stores a recipe from a url scan', function () {
        Http::fake([
            'http://1.1.1.1/*' => Http::response('<html><body><h1>Web Pancakes</h1><p>Mix and cook.</p></body></html>'),
            '*generativelanguage*' => Http::response(fakeUrlGeminiResponse(sampleUrlRecipePayload())),
        ]);

        $user = User::factory()->create();

        $scan = RecipeScan::factory()->for($user)->url('http://1.1.1.1/recipe')->create();

        (new ProcessRecipeScan($scan))->handle(
            app(RecipeExtractor::class),
            app(StoreExtractedRecipe::class),
            urlFetcher(),
        );

        $scan->refresh();

        expect($scan->status)->toBe(ScanStatus::Ready)
            ->and($scan->recipe_id)->not->toBeNull();

        $recipe = Recipe::find($scan->recipe_id);

        expect($recipe->title)->toBe('Web Pancakes')
            ->and($recipe->source_type)->toBe('url')
            ->and($recipe->source_url)->toBe('http://1.1.1.1/recipe');
    });

    it('marks the scan failed when the url is unsafe', function () {
        $user = User::factory()->create();
        $scan = RecipeScan::factory()->for($user)->url('http://127.0.0.1/admin')->create();

        try {
            (new ProcessRecipeScan($scan))->handle(
                app(RecipeExtractor::class),
                app(StoreExtractedRecipe::class),
                urlFetcher(),
            );
        } catch (RecipeExtractionException $e) {
            $scan->markFailed($e->getMessage());
        }

        expect($scan->fresh()->status)->toBe(ScanStatus::Failed);
    });
});

describe('scans.create Volt component url mode', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    it('creates a url scan and dispatches the job', function () {
        Queue::fake();

        Volt::test('scans.create')
            ->set('mode', 'url')
            ->set('url', 'https://example.com/recipe')
            ->call('scanUrl')
            ->assertHasNoErrors()
            ->assertRedirect();

        $scan = RecipeScan::sole();

        expect($scan->source_type)->toBe('url')
            ->and($scan->source_url)->toBe('https://example.com/recipe')
            ->and($scan->status)->toBe(ScanStatus::Pending);

        Queue::assertPushed(ProcessRecipeScan::class, fn ($job) => $job->scan->is($scan));
    });

    it('rejects an invalid url', function () {
        Queue::fake();

        Volt::test('scans.create')
            ->set('mode', 'url')
            ->set('url', 'not-a-url')
            ->call('scanUrl')
            ->assertHasErrors('url');

        expect(RecipeScan::count())->toBe(0);
        Queue::assertNothingPushed();
    });
});
