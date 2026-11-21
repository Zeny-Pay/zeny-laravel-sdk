<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Crypto;

use Zeny\Domain\Webhook\HmacVerifier;

/** HmacVerifier over ext-hash; `hash_equals` compares in constant time. */
final class NativeHmacVerifier implements HmacVerifier
{
    public function verify(#[\SensitiveParameter] string $secret, string $message, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $message, $secret, true), $signature);
    }
}
