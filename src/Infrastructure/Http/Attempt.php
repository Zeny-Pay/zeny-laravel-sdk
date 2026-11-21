<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use Zeny\Domain\Errors\ZenyError;
use Zeny\Domain\ZenyObject;

/** One attempt: a parsed success, or an error plus what the retry policy needs to judge it. */
final class Attempt
{
    /** @param  ZenyObject<array<string, mixed>>|null  $object */
    private function __construct(
        public readonly ?ZenyObject $object,
        public readonly ?ZenyError $error,
        public readonly Outcome $outcome,
        public readonly ?int $retryAfterSec,
    ) {}

    /** @param  ZenyObject<array<string, mixed>>  $object */
    public static function succeeded(ZenyObject $object): self
    {
        return new self($object, null, Outcome::response($object->lastResponse->statusCode, null), null);
    }

    public static function failed(ZenyError $error, Outcome $outcome, ?int $retryAfterSec): self
    {
        return new self(null, $error, $outcome, $retryAfterSec);
    }
}
