<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** The API is shedding load (`overloaded`); `retryAfterSec` is the Retry-After header, if sent. */
final class OverloadedError extends ThrottledError {}
