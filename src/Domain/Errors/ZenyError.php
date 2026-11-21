<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

use RuntimeException;
use Throwable;

/**
 * Base class of every error the SDK throws; an unknown server error type maps
 * here, so a `catch (ZenyError)` always works.
 *
 * @phpstan-type ErrorDetail array{param: string, code: string, message: string}
 */
class ZenyError extends RuntimeException
{
    /**
     * The machine-readable error code, such as `amount_too_small`. Exception
     * declares `$code` untyped, so PHP allows no typed redeclaration; it is
     * always a string here, and `getCode()` returns the same value.
     *
     * @var string
     */
    public $code;

    /**
     * @param  list<ErrorDetail>  $details  field-level problems the API reported
     * @param  string|null  $idempotencyKey  the key the failed POST carried (generated
     *                                       when you passed none); null for GET. Retry a failed or unknown-outcome POST
     *                                       with this value: a fresh key could charge twice. Not a secret.
     */
    public function __construct(
        public readonly string $type,
        string $code,
        string $message,
        public readonly ?string $param = null,
        public readonly array $details = [],
        public readonly ?string $requestId = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $idempotencyKey = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}
