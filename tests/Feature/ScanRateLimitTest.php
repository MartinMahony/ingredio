<?php

use App\Jobs\ProcessRecipeScan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('a user is blocked after exceeding the per-minute scan limit', function () {
    config()->set('scanning.rate_limit.per_minute', 2);
    config()->set('scanning.rate_limit.per_day', 100);

    Storage::fake('local');
    Queue::fake();

    $this->actingAs(User::factory()->create());

    foreach (range(1, 2) as $_) {
        Volt::test('scans.create')
            ->set('file', UploadedFile::fake()->image('recipe.png'))
            ->call('scan')
            ->assertHasNoErrors();
    }

    Volt::test('scans.create')
        ->set('file', UploadedFile::fake()->image('recipe.png'))
        ->call('scan')
        ->assertHasErrors('file');

    Queue::assertPushed(ProcessRecipeScan::class, 2);
});

test('a user is blocked after exceeding the daily scan limit', function () {
    config()->set('scanning.rate_limit.per_minute', 100);
    config()->set('scanning.rate_limit.per_day', 1);

    Storage::fake('local');
    Queue::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('scans.create')
        ->set('file', UploadedFile::fake()->image('recipe.png'))
        ->call('scan')
        ->assertHasNoErrors();

    Volt::test('scans.create')
        ->set('file', UploadedFile::fake()->image('recipe.png'))
        ->call('scan')
        ->assertHasErrors('file');

    Queue::assertPushed(ProcessRecipeScan::class, 1);
});

test('the url scan action is also rate limited', function () {
    config()->set('scanning.rate_limit.per_minute', 1);
    config()->set('scanning.rate_limit.per_day', 100);

    Queue::fake();

    $this->actingAs(User::factory()->create());

    Volt::test('scans.create')
        ->set('mode', 'url')
        ->set('url', 'https://example.com/recipe-1')
        ->call('scanUrl')
        ->assertHasNoErrors();

    Volt::test('scans.create')
        ->set('mode', 'url')
        ->set('url', 'https://example.com/recipe-2')
        ->call('scanUrl')
        ->assertHasErrors('url');

    Queue::assertPushed(ProcessRecipeScan::class, 1);
});

test('scan rate limits are tracked per user', function () {
    config()->set('scanning.rate_limit.per_minute', 1);
    config()->set('scanning.rate_limit.per_day', 100);

    Storage::fake('local');
    Queue::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);
    Volt::test('scans.create')
        ->set('file', UploadedFile::fake()->image('recipe.png'))
        ->call('scan')
        ->assertHasNoErrors();

    $this->actingAs($userB);
    Volt::test('scans.create')
        ->set('file', UploadedFile::fake()->image('recipe.png'))
        ->call('scan')
        ->assertHasNoErrors();

    Queue::assertPushed(ProcessRecipeScan::class, 2);
});
