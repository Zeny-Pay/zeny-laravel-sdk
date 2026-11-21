<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** The request conflicts with the resource's current state (`conflict`). */
final class ConflictError extends ZenyError {}
