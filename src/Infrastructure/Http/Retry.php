<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/** The retry policy: which outcomes are safe to retry and how long to wait. */
final class Retry
{
    /** Base backoff delay in milliseconds (attempt 0). */
    public const BASE_DELAY_MS = 500;

    /** Upper bound of the exponential backoff window in milliseconds. */
    public const MAX_DELAY_MS = 8000;

    /** Longest Retry-After the SDK honours, in seconds. */
    public const MAX_RETRY_AFTER_SEC = 60;

    /** The one 4xx code that is safe to retry: the first request with this key is still running. */
    public const IDEMPOTENCY_IN_PROGRESS_CODE = 'idempotency_key_in_progress';

    private const HTTP_DATE = '/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/';

    /** No response, any 5xx, 429, or an in-progress idempotency key. Every other 4xx is final. */
    public static function isRetryable(Outcome $outcome): bool
    {
        return $outcome->isNetwork()
            || $outcome->status >= 500
            || $outcome->status === 429
            || $outcome->code === self::IDEMPOTENCY_IN_PROGRESS_CODE;
    }

    /**
     * Parses Retry-After (delta-seconds or IMF-fixdate) into whole seconds,
     * capped at 60. Null when absent or invalid. `$nowMs` keeps it pure.
     */
    public static function parseRetryAfter(?string $header, int $nowMs): ?int
    {
        if ($header === null) {
            return null;
        }
        $value = trim($header);
        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) min((float) $value, self::MAX_RETRY_AFTER_SEC);
        }
        $date = preg_match(self::HTTP_DATE, $value) === 1
            ? DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'))
            : false;
        if ($date === false) {
            return null;
        }
        $seconds = (int) ceil(($date->getTimestamp() * 1000 - $nowMs) / 1000);

        return min(max(0, $seconds), self::MAX_RETRY_AFTER_SEC);
    }

    /**
     * Delay before the next attempt (`$attempt` is 0-based). A non-null
     * Retry-After, 0 included, is honoured; otherwise full-jitter backoff.
     *
     * @param  Closure(): float  $random  returns a value in [0, 1]
     */
    public static function delayMs(int $attempt, ?int $retryAfterSec, Closure $random): int
    {
        if ($retryAfterSec !== null) {
            return $retryAfterSec * 1000;
        }

        return (int) ($random() * min(self::MAX_DELAY_MS, self::BASE_DELAY_MS * 2 ** $attempt));
    }
}
