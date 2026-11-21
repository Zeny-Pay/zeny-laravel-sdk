<?php

declare(strict_types=1);

use Zeny\Infrastructure\Http\Outcome;
use Zeny\Infrastructure\Http\Retry;

const RETRY_NOW_MS = 1_700_000_000_000;

$retryable = [
    'network' => Outcome::network(),
    '500' => Outcome::response(500, 'internal'),
    '503' => Outcome::response(503, 'provider_unavailable'),
    '429' => Outcome::response(429, 'rate_limited'),
    '409 in progress' => Outcome::response(409, 'idempotency_key_in_progress'),
];
foreach ($retryable as $name => $outcome) {
    test("{$name} is retryable", function () use ($outcome): void {
        expect(Retry::isRetryable($outcome))->toBeTrue();
    });
}

$final = [
    '400' => Outcome::response(400, 'invalid'),
    '401' => Outcome::response(401, 'unauthenticated'),
    '404' => Outcome::response(404, 'not_found'),
    'other 409' => Outcome::response(409, 'idempotency_key_reused'),
];
foreach ($final as $name => $outcome) {
    test("{$name} is not retryable", function () use ($outcome): void {
        expect(Retry::isRetryable($outcome))->toBeFalse();
    });
}

$retryAfter = [
    'absent' => [null, null],
    'seconds' => ['7', 7],
    'padded seconds' => [' 3 ', 3],
    'zero' => ['0', 0],
    'seconds over the cap' => ['600', 60],
    'a huge number' => ['99999999999999999999999', 60],
    'a date 10 s ahead' => [gmdate('D, d M Y H:i:s', 1_700_000_010).' GMT', 10],
    'a date 0.5 s ahead rounds up' => [gmdate('D, d M Y H:i:s', 1_700_000_001).' GMT', 1],
    'a date past the cap' => [gmdate('D, d M Y H:i:s', 1_700_003_600).' GMT', 60],
    'a past date' => [gmdate('D, d M Y H:i:s', 1_699_999_000).' GMT', 0],
    'garbage' => ['soon', null],
    'a decimal' => ['1.5', null],
    'a signed number' => ['+5', null],
    'a negative number' => ['-5', null],
    'a non-IMF date' => ['2026-11-21T00:00:00Z', null],
];
foreach ($retryAfter as $name => [$header, $expected]) {
    test("Retry-After {$name}", function () use ($name, $header, $expected): void {
        $now = $name === 'a date 0.5 s ahead rounds up' ? RETRY_NOW_MS + 500 : RETRY_NOW_MS;

        expect(Retry::parseRetryAfter($header, $now))->toBe($expected);
    });
}

test('backoff uses full jitter over 500 * 2^attempt', function (): void {
    expect(Retry::delayMs(2, null, fn (): float => 0.5))->toBe(1000);
});

test('backoff is capped at 8000 ms', function (): void {
    expect(Retry::delayMs(10, null, fn (): float => 1.0))->toBe(8000);
});

test('Retry-After wins over backoff', function (): void {
    expect(Retry::delayMs(0, 4, fn (): float => 1.0))->toBe(4000);
});

test('Retry-After 0 is honoured', function (): void {
    expect(Retry::delayMs(3, 0, fn (): float => 1.0))->toBe(0);
});
