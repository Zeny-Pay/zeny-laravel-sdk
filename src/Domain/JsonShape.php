<?php

declare(strict_types=1);

namespace Zeny\Domain;

use stdClass;
use Zeny\Generated\MerchantV1;

/**
 * Restores JSON objects that PHP arrays lose: an empty array encodes as `[]`
 * and a map with numeric keys as a list, so every property the contract
 * declares as a map or free-form object (`metadata`, `card`, ...) is turned
 * back into an object, at any depth. The top level is always an object.
 */
final class JsonShape
{
    /** @param array<mixed> $body */
    public static function encode(array $body): stdClass
    {
        return (object) self::walk($body);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function walk(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = is_array($item) ? self::field($key, $item) : $item;
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $item
     * @return array<mixed>|stdClass
     */
    private static function field(int|string $key, array $item): array|stdClass
    {
        $walked = self::walk($item);

        return is_string($key) && in_array($key, MerchantV1::OBJECT_FIELDS, true) ? (object) $walked : $walked;
    }
}
