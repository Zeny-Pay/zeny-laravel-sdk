<?php

declare(strict_types=1);

namespace Zeny\Domain;

use InvalidArgumentException;

/**
 * The id of an API object, such as `pay_...`. Only emptiness is checked: the
 * API owns its prefixes, and a new one must not need an SDK release.
 */
final class ResourceId
{
    private function __construct(public readonly string $value) {}

    public static function parse(mixed $raw): self
    {
        if (! is_string($raw) || $raw === '') {
            throw new InvalidArgumentException('id must be a non-empty string');
        }

        return new self($raw);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
