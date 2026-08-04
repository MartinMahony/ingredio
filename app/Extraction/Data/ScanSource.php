<?php

namespace App\Extraction\Data;

final class ScanSource
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $contents,
    ) {}

    public static function fromContents(string $contents, string $mimeType): self
    {
        return new self($mimeType, $contents);
    }

    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }
}
