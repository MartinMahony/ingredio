<?php

namespace App\Extraction\Data;

final class ExtractedIngredient
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $quantity = null,
        public readonly ?string $unit = null,
        public readonly ?string $group = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            quantity: Normalize::nullableString($data['quantity'] ?? null),
            unit: Normalize::nullableString($data['unit'] ?? null),
            group: Normalize::nullableString($data['group'] ?? null),
            note: Normalize::nullableString($data['note'] ?? null),
        );
    }
}
