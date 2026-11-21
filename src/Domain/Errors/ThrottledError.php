<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

use Throwable;

/**
 * An error the API sends while shedding work, which may carry `Retry-After`.
 * Shared by RateLimitError and OverloadedError so both keep `retryAfterSec`.
 *
 * @phpstan-import-type ErrorDetail from ZenyError
 */
abstract class ThrottledError extends ZenyError
{
    /**
     * @param  list<ErrorDetail>  $details
     * @param  int|null  $retryAfterSec  the Retry-After header in whole seconds (capped at 60), if sent
     */
    public function __construct(
        string $type,
        string $code,
        string $message,
        ?string $param = null,
        array $details = [],
        ?string $requestId = null,
        ?int $statusCode = null,
        ?string $idempotencyKey = null,
        ?Throwable $previous = null,
        public readonly ?int $retryAfterSec = null,
    ) {
        parent::__construct($type, $code, $message, $param, $details, $requestId, $statusCode, $idempotencyKey, $previous);
    }
}
