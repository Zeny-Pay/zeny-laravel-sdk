<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use Closure;

/** Side effects the transport needs, injected so tests run without real time, randomness or ids. */
final class TransportDeps
{
    /** @var Closure(int): void sleeps for the given milliseconds */
    public readonly Closure $sleep;

    /** @var Closure(): float a value in [0, 1] */
    public readonly Closure $random;

    /** @var Closure(): string a fresh idempotency key */
    public readonly Closure $uuid;

    /** @var Closure(): int the current time in milliseconds */
    public readonly Closure $now;

    /**
     * @param  (Closure(int): void)|null  $sleep
     * @param  (Closure(): float)|null  $random
     * @param  (Closure(): string)|null  $uuid
     * @param  (Closure(): int)|null  $now
     */
    public function __construct(?Closure $sleep = null, ?Closure $random = null, ?Closure $uuid = null, ?Closure $now = null)
    {
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
        $this->random = $random ?? static fn (): float => random_int(0, PHP_INT_MAX) / PHP_INT_MAX;
        $this->uuid = $uuid ?? self::uuidV4(...);
        $this->now = $now ?? static fn (): int => (int) floor(microtime(true) * 1000);
    }

    /** A random RFC 9562 version 4 UUID. */
    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
