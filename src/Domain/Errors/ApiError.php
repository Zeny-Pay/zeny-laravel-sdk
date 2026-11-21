<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** Unexpected server failure, or a response that was not the documented shape (`api_error`). */
final class ApiError extends ZenyError {}
