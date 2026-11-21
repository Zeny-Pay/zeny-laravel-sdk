<?php

declare(strict_types=1);

namespace Zeny\Scripts;

/**
 * Narrows decoded JSON for the generator, so PHPStan sees typed arrays and a
 * malformed contract fails loudly instead of producing a wrong type.
 */
final class Json
{
    /** @return array<string, mixed> */
    public static function object(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /** @return list<mixed> */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    public static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return array<string, mixed> */
    public static function decodeFile(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException("{$path} is not a JSON object");
        }

        return self::object($decoded);
    }
}
