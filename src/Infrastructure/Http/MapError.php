<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use stdClass;
use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\AuthenticationError;
use Zeny\Domain\Errors\ConflictError;
use Zeny\Domain\Errors\IdempotencyError;
use Zeny\Domain\Errors\InvalidRequestError;
use Zeny\Domain\Errors\NotFoundError;
use Zeny\Domain\Errors\OverloadedError;
use Zeny\Domain\Errors\PermissionError;
use Zeny\Domain\Errors\ProviderUnavailableError;
use Zeny\Domain\Errors\RateLimitError;
use Zeny\Domain\Errors\ThrottledError;
use Zeny\Domain\Errors\ZenyError;

/**
 * Turns a non-2xx response into a typed error. The body is untrusted network
 * input, so its shape is checked field by field; anything unusable becomes an
 * ApiError with code `unexpected_response`, and an unknown `type` a plain ZenyError.
 * The body is decoded with JSON objects kept as stdClass, so `{}` (a record) and
 * `[]` (a list) stay distinct, as they are in zeny-node.
 *
 * @phpstan-import-type ErrorDetail from ZenyError
 */
final class MapError
{
    /** @var array<string, class-string<ZenyError>> */
    private const REGISTRY = [
        'invalid_request' => InvalidRequestError::class,
        'authentication' => AuthenticationError::class,
        'permission' => PermissionError::class,
        'not_found' => NotFoundError::class,
        'conflict' => ConflictError::class,
        'idempotency_error' => IdempotencyError::class,
        'provider_unavailable' => ProviderUnavailableError::class,
        'api_error' => ApiError::class,
    ];

    /** Types that may carry Retry-After. @var array<string, class-string<ThrottledError>> */
    private const THROTTLED = [
        'rate_limit' => RateLimitError::class,
        'overloaded' => OverloadedError::class,
    ];

    public static function map(int $status, mixed $body, ?string $requestId, ?int $retryAfterSec = null, ?string $idempotencyKey = null): ZenyError
    {
        $error = $body instanceof stdClass ? self::record($body->error ?? null) : null;
        if ($error === null) {
            return self::unexpected($status, $requestId, $idempotencyKey);
        }
        $type = self::str($error['type'] ?? null) ?? 'api_error';
        $args = self::arguments($error, $type, $status, $requestId, $idempotencyKey);
        if (isset(self::THROTTLED[$type])) {
            return new (self::THROTTLED[$type])(...$args, retryAfterSec: $retryAfterSec);
        }

        return new (self::REGISTRY[$type] ?? ZenyError::class)(...$args);
    }

    /**
     * Constructor arguments shared by every error class, in order.
     *
     * @param  array<mixed>  $error
     * @return array{string, string, string, string|null, list<ErrorDetail>, string|null, int, string|null}
     */
    private static function arguments(array $error, string $type, int $status, ?string $requestId, ?string $idempotencyKey): array
    {
        return [
            $type,
            self::str($error['code'] ?? null) ?? 'unknown',
            self::str($error['message'] ?? null) ?? "API error (HTTP {$status})",
            self::str($error['param'] ?? null),
            self::details($error['details'] ?? null),
            self::str($error['requestId'] ?? null) ?? $requestId,
            $status,
            $idempotencyKey,
        ];
    }

    private static function unexpected(int $status, ?string $requestId, ?string $idempotencyKey): ApiError
    {
        return new ApiError(
            'api_error',
            'unexpected_response',
            "Unexpected response from the API (HTTP {$status})",
            requestId: $requestId,
            statusCode: $status,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return list<ErrorDetail> */
    private static function details(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $details = [];
        foreach ($value as $detail) {
            $detail = self::record($detail);
            if ($detail !== null && is_string($detail['param'] ?? null) && is_string($detail['code'] ?? null) && is_string($detail['message'] ?? null)) {
                $details[] = ['param' => $detail['param'], 'code' => $detail['code'], 'message' => $detail['message']];
            }
        }

        return $details;
    }

    /**
     * A JSON object as an array of its fields; null for anything else, a JSON array included.
     *
     * @return array<mixed>|null
     */
    private static function record(mixed $value): ?array
    {
        return $value instanceof stdClass ? get_object_vars($value) : null;
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
