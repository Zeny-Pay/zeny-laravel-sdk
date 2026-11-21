<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

/** One API call. `path` is relative to `/api/merchant/v1` and already URL-encoded. */
final class RequestSpec
{
    /**
     * @param  'GET'|'POST'  $method
     * @param  array<string, string|int|null>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body  POST always sends one (`{}` when null or empty)
     * @param  string|null  $idempotencyKey  the caller's key; only used for POST
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?array $body = null,
        public readonly ?string $idempotencyKey = null,
    ) {}

    /** @param  array<string, string|int|null>  $query */
    public static function get(string $path, array $query = []): self
    {
        return new self('GET', $path, $query);
    }

    /** @param  array<string, mixed>|null  $body */
    public static function post(string $path, ?array $body = null, ?string $idempotencyKey = null): self
    {
        return new self('POST', $path, body: $body, idempotencyKey: $idempotencyKey);
    }
}
