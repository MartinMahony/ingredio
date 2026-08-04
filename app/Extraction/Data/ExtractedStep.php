<?php

namespace App\Extraction\Data;

final class ExtractedStep
{
    public function __construct(
        public readonly string $instruction,
        public readonly ?int $minutes = null,
        public readonly ?string $group = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            instruction: trim((string) ($data['instruction'] ?? '')),
            minutes: Normalize::nullableInt($data['minutes'] ?? null),
            group: Normalize::nullableString($data['group'] ?? null),
        );
    }
}
