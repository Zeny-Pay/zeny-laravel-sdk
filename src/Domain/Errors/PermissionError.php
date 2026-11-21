<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** The key is valid but not allowed to do this (`permission`). */
final class PermissionError extends ZenyError {}
