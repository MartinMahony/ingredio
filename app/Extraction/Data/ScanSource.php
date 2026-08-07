<?php

namespace App\Extraction\Data;

final class ScanSource
{
    private const MAX_TEXT_LENGTH = 20_000;

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
        return new self('text/plain', self::limit($text), isText: true);
    }

    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    private static function limit(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_TEXT_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH).'…';
    }
}
