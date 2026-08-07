<?php

use App\Extraction\Data\ScanSource;

it('does not alter short text sources', function () {
    $source = ScanSource::fromText('Short recipe text.');

    expect($source->isText)->toBeTrue()
        ->and($source->contents)->toBe('Short recipe text.');
});

it('truncates long text sources to the maximum length with an ellipsis', function () {
    $longText = str_repeat('a', 25_000);

    $source = ScanSource::fromText($longText);

    expect($source->isText)->toBeTrue()
        ->and(mb_strlen($source->contents))->toBe(20_001)
        ->and(str_ends_with($source->contents, '…'))->toBeTrue();
});

it('still exposes binary sources unchanged', function () {
    $source = ScanSource::fromContents('fake-image-bytes', 'image/png');

    expect($source->isText)->toBeFalse()
        ->and($source->contents)->toBe('fake-image-bytes');
});
