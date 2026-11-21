<?php

declare(strict_types=1);

namespace Zeny\Domain;

/**
 * Per-call options for a POST. `idempotencyKey` replaces the key the SDK would
 * generate; reuse the same key to retry the same intent safely.
 */
final class RequestOptions
{
    public function __construct(public readonly ?string $idempotencyKey = null) {}
}
