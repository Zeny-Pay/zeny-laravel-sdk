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
     * @param  bool  $bodiless  a POST that sends no body and no Idempotency-Key
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?array $body = null,
        public readonly ?string $idempotencyKey = null,
        public readonly bool $bodiless = false,
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

    /** A state change the API makes idempotent itself: POST with no body and no Idempotency-Key. */
    public static function action(string $path): self
    {
        return new self('POST', $path, bodiless: true);
    }
}
