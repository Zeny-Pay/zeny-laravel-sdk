<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

/** What one attempt produced: no response at all, or a response with its error code. */
final class Outcome
{
    private function __construct(
        public readonly ?int $status,
        public readonly ?string $code,
    ) {}

    public static function network(): self
    {
        return new self(null, null);
    }

    public static function response(int $status, ?string $code): self
    {
        return new self($status, $code);
    }

    public function isNetwork(): bool
    {
        return $this->status === null;
    }
}
