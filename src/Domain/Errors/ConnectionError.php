<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

use Throwable;

/**
 * The request never got a response (`timeout` or `network_error`). The server
 * may still have acted on it, so the outcome is unknown: retry with the same
 * `idempotencyKey`, or read the object.
 */
final class ConnectionError extends ZenyError
{
    public readonly bool $outcomeUnknown;

    public function __construct(string $code, string $message, ?string $idempotencyKey = null, ?Throwable $previous = null)
    {
        parent::__construct('connection_error', $code, $message, idempotencyKey: $idempotencyKey, previous: $previous);
        $this->outcomeUnknown = true;
    }
}
