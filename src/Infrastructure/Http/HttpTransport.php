<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use Zeny\Domain\ZenyObject;

/** What the repositories call to reach the API. */
interface HttpTransport
{
    /**
     * Sends the request, retrying safe failures, and returns the body or
     * throws a ZenyError.
     *
     * @return ZenyObject<array<string, mixed>>
     */
    public function request(RequestSpec $spec): ZenyObject;
}
