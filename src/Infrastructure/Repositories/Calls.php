<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Infrastructure\Http\HttpTransport;
use Zeny\Infrastructure\Http\RequestSpec;

/** The two calls every repository makes; null query values are dropped by the transport. */
final class Calls
{
    /**
     * @param  array<string, string|int|null>  $query
     * @return ZenyObject<array<string, mixed>>
     */
    public static function get(HttpTransport $transport, string $path, array $query = []): ZenyObject
    {
        return $transport->request(RequestSpec::get($path, $query));
    }

    /**
     * Sends a POST, forwarding the caller's Idempotency-Key only when one was given.
     *
     * @param  array<string, mixed>  $body
     * @return ZenyObject<array<string, mixed>>
     */
    public static function post(HttpTransport $transport, string $path, array $body, ?RequestOptions $options = null): ZenyObject
    {
        return $transport->request(RequestSpec::post($path, $body, $options?->idempotencyKey));
    }
}
