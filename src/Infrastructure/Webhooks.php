<?php

declare(strict_types=1);

namespace Zeny\Infrastructure;

use Closure;
use InvalidArgumentException;
use Zeny\Domain\Webhook\HmacVerifier;
use Zeny\Domain\Webhook\Webhook;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Crypto\NativeHmacVerifier;

/**
 * Webhook helpers that need no API key, so a receiver can verify without a
 * client. Reached through `Zeny::webhooks()`.
 *
 * @phpstan-import-type ZenyEvent from MerchantV1
 */
final class Webhooks
{
    private readonly HmacVerifier $verifier;

    public function __construct()
    {
        $this->verifier = new NativeHmacVerifier;
    }

    /**
     * Verifies a webhook and returns its event; see Webhook::constructEventWith
     * for the rules and error codes. Pass the raw request body, byte for byte.
     * Verifying does not stop replays inside the tolerance window: store each
     * event `id` under a unique constraint.
     *
     * @param  mixed  $rawBody  the raw body string
     * @param  mixed  $headers  header name => value or list of values, any case
     * @param  array<string, mixed>  $options  `toleranceSec` (default 300); `now`, a Closure returning milliseconds, for tests
     * @return ZenyEvent
     */
    public function constructEvent(mixed $rawBody, mixed $headers, #[\SensitiveParameter] mixed $secret, array $options = []): array
    {
        return Webhook::constructEventWith($this->verifier, self::clock($options['now'] ?? null), $rawBody, $headers, $secret, $options);
    }

    /** @return Closure(): (int|float) */
    private static function clock(mixed $now): Closure
    {
        if ($now === null) {
            return fn (): float => microtime(true) * 1000;
        }
        if (! $now instanceof Closure) {
            throw new InvalidArgumentException('now must be a Closure returning milliseconds.');
        }

        return fn (): int|float => self::milliseconds($now());
    }

    private static function milliseconds(mixed $value): int|float
    {
        return is_int($value) || is_float($value) ? $value : NAN;
    }
}
