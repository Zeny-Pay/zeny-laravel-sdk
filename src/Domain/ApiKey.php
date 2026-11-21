<?php

declare(strict_types=1);

namespace Zeny\Domain;

use InvalidArgumentException;

/**
 * A validated secret key. The raw text lives in a private property and every
 * way of printing the object (string cast, var_dump, print_r, json_encode)
 * shows a redacted form, so a key never lands in a log by accident.
 */
final class ApiKey implements \JsonSerializable
{
    private const LIVE_PREFIX = 'sk_live_';

    private const TEST_PREFIX = 'sk_test_';

    // Printable ASCII only: a control character would end up in a header
    // error message that quotes the key.
    private const PATTERN = '/^sk_(live|test)_[\x21-\x7E]+$/';

    /** Environment chosen by the key prefix: `live` or `test`. */
    public readonly string $mode;

    private function __construct(#[\SensitiveParameter] private readonly string $secret)
    {
        $this->mode = str_starts_with($secret, self::LIVE_PREFIX) ? 'live' : 'test';
    }

    /**
     * Validates `$raw` as a `sk_live_` or `sk_test_` key. The message never
     * echoes the input, so a mistyped key cannot leak into error reports.
     */
    public static function parse(#[\SensitiveParameter] mixed $raw): self
    {
        if (! is_string($raw) || preg_match(self::PATTERN, $raw) !== 1) {
            throw new InvalidArgumentException(
                'apiKey must be a secret key starting with "'.self::LIVE_PREFIX.'" or "'.self::TEST_PREFIX.'"',
            );
        }

        return new self($raw);
    }

    /** The secret itself. Read it only where the bearer header is built. */
    public function value(): string
    {
        return $this->secret;
    }

    public function __toString(): string
    {
        return 'ApiKey(sk_'.$this->mode.'_***)';
    }

    /** @return array{key: string, mode: string} */
    public function __debugInfo(): array
    {
        return ['key' => (string) $this, 'mode' => $this->mode];
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** A serialised key would sit in a cache or queue in clear text, so refuse. */
    public function __serialize(): never
    {
        throw new \LogicException('An API key cannot be serialized.');
    }
}
