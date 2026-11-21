<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** The Idempotency-Key was reused with a different request (`idempotency_error`). */
final class IdempotencyError extends ZenyError {}
