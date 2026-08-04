<?php

use App\Enums\ScanStatus;
use App\Jobs\ProcessRecipeScan;
use App\Models\Recipe;
use App\Models\RecipeScan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config()->set('scanning.disk', 'local');
    $this->actingAs(User::factory()->create());
});

it('stores the upload, creates a scan, and dispatches the job', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->image('recipe.png');

    Volt::test('scans.create')
        ->set('file', $file)
        ->call('scan')
        ->assertHasNoErrors()
        ->assertRedirect();

    $scan = RecipeScan::sole();

    expect($scan->status)->toBe(ScanStatus::Pending)
        ->and($scan->source_type)->toBe('image')
        ->and($scan->source_disk)->toBe('local')
        ->and($scan->original_filename)->toBe('recipe.png')
        ->and($scan->user_id)->toBe(auth()->id());

    Storage::disk('local')->assertExists($scan->source_path);
    Queue::assertPushed(ProcessRecipeScan::class, fn ($job) => $job->scan->is($scan));
});

it('detects pdf uploads as a pdf source type', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('recipe.pdf', 100, 'application/pdf');

    Volt::test('scans.create')
        ->set('file', $file)
        ->call('scan')
        ->assertHasNoErrors();

    expect(RecipeScan::sole()->source_type)->toBe('pdf');
});

it('rejects unsupported file types', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('recipe.txt', 10, 'text/plain');

    Volt::test('scans.create')
        ->set('file', $file)
        ->call('scan')
        ->assertHasErrors('file');

    expect(RecipeScan::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects files that exceed the size limit', function () {
    config()->set('scanning.max_upload_kb', 50);

    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('recipe.png', 100, 'image/png');

    Volt::test('scans.create')
        ->set('file', $file)
        ->call('scan')
        ->assertHasErrors('file');

    expect(RecipeScan::count())->toBe(0);
});

it('shows a processing state while the scan is pending', function () {
    $scan = RecipeScan::factory()->for(auth()->user())->create([
        'status' => ScanStatus::Processing,
    ]);

    Volt::test('scans.show', ['scan' => $scan])
        ->assertSee('Reading your recipe')
        ->assertNoRedirect();
});

it('redirects to the review screen when the scan is ready', function () {
    $recipe = Recipe::factory()->for(auth()->user())->create();
    $scan = RecipeScan::factory()->for(auth()->user())->create([
        'status' => ScanStatus::Ready,
        'recipe_id' => $recipe->id,
    ]);

    Volt::test('scans.show', ['scan' => $scan])
        ->call('poll')
        ->assertRedirect(route('recipes.edit', $recipe));
});

it('shows the error when the scan failed', function () {
    $scan = RecipeScan::factory()->for(auth()->user())->create([
        'status' => ScanStatus::Failed,
        'error' => 'The model returned no content.',
    ]);

    Volt::test('scans.show', ['scan' => $scan])
        ->assertSee('read that recipe')
        ->assertSee('The model returned no content.');
});

it('forbids viewing another users scan', function () {
    $scan = RecipeScan::factory()->create();

    Volt::test('scans.show', ['scan' => $scan])
        ->assertForbidden();
});
