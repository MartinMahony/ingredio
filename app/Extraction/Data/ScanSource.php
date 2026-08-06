<?php

namespace App\Extraction\Data;

final class ScanSource
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $contents,
        public readonly bool $isText = false,
    ) {}

    public static function fromContents(string $contents, string $mimeType): self
    {
        return new self($mimeType, $contents);
    }

    /**
     * Build a source from plain text, e.g. cleaned webpage content.
     */
    public static function fromText(string $text): self
    {
        return new self('text/plain', $text, isText: true);
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
