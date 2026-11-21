<?php

declare(strict_types=1);

namespace Zeny\Domain;

/** Facts about the HTTP response that produced a result. */
final class ResponseMeta
{
    /**
     * @param  string|null  $requestId  the `X-Request-ID` header, when sent
     * @param  bool  $idempotentReplayed  true when the API answered from a stored result (`Idempotent-Replayed: true`)
     * @param  string|null  $idempotencyKey  the Idempotency-Key that was sent; null for GET
     * @param  array<string, list<string>>  $headers  response headers, names lower-cased
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $requestId,
        public readonly bool $idempotentReplayed,
        public readonly ?string $idempotencyKey,
        public readonly array $headers,
    ) {}
}
