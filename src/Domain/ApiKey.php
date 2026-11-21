<?php

declare(strict_types=1);

namespace Zeny\Domain;

use InvalidArgumentException;
use LogicException;
use stdClass;
use WeakMap;

/**
 * A validated secret key. The raw text is not a property of the object: it
 * sits in a static WeakMap keyed by an empty handle, so nothing that walks an
 * object's properties (var_dump, print_r, var_export, Symfony VarDumper and
 * so dd(), Telescope or Ignition) can reach it. Printing the object shows a
 * redacted form instead.
 */
final class ApiKey implements \JsonSerializable
{
    private const LIVE_PREFIX = 'sk_live_';

    private const TEST_PREFIX = 'sk_test_';

    // Printable ASCII only, and `\z` rather than `$`, which also accepts a
    // trailing newline: a control character would end up in a header error
    // message that quotes the key.
    private const PATTERN = '/^sk_(live|test)_[\x21-\x7E]+\z/';

    /** @var WeakMap<stdClass, string>|null handle => secret; an entry goes when its handle does */
    private static ?WeakMap $secrets = null;

    /** Environment chosen by the key prefix: `live` or `test`. */
    public readonly string $mode;

    /** Shared by clones, so a cloned key still reads its secret. */
    private readonly stdClass $handle;

    private function __construct(#[\SensitiveParameter] string $secret)
    {
        $this->mode = str_starts_with($secret, self::LIVE_PREFIX) ? 'live' : 'test';
        $this->handle = new stdClass;
        self::secrets()[$this->handle] = $secret;
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
        return self::secrets()[$this->handle] ?? throw new LogicException('The API key secret is missing.');
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
        throw new LogicException('An API key cannot be serialized.');
    }

    /** @return WeakMap<stdClass, string> */
    private static function secrets(): WeakMap
    {
        return self::$secrets ??= new WeakMap;
    }
}
