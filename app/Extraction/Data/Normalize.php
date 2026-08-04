<?php

namespace App\Extraction\Data;

final class Normalize
{
    public static function nullableString(mixed $value): ?string
    {
        if (is_array($value)) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
