<?php

declare(strict_types=1);

namespace Zeny\Domain;

use InvalidArgumentException;

/** A caller-chosen Idempotency-Key: 1-255 printable ASCII characters. */
final class IdempotencyKey
{
    private const PATTERN = '/^[\x20-\x7E]{1,255}$/';

    private function __construct(public readonly string $value) {}

    /** Validates `$raw`. The message never echoes the key. */
    public static function parse(mixed $raw): self
    {
        if (! is_string($raw) || preg_match(self::PATTERN, $raw) !== 1) {
            throw new InvalidArgumentException('idempotencyKey must be 1-255 printable ASCII characters');
        }

        return new self($raw);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
