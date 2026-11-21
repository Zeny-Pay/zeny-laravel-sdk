<?php

declare(strict_types=1);

namespace Zeny\Domain\Webhook;

/** Port for the HMAC-SHA256 check, so the webhook rules stay free of any crypto extension. */
interface HmacVerifier
{
    /**
     * True when `$signature` (32 raw bytes) is the HMAC-SHA256 of `$message`
     * under `$secret`. Implementations must compare in constant time.
     */
    public function verify(#[\SensitiveParameter] string $secret, string $message, string $signature): bool;
}
