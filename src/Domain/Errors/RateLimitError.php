<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** Too many requests (`rate_limit`); `retryAfterSec` is the Retry-After header, if sent. */
final class RateLimitError extends ThrottledError {}
