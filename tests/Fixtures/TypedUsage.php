<?php

declare(strict_types=1);

namespace Zeny\Tests\Fixtures;

use Zeny\Generated\MerchantV1;

/**
 * Never run: PHPStan checks it, so a generated alias that PHPStan can't parse,
 * or one that stops narrowing, fails `composer typecheck`.
 *
 * @phpstan-import-type Money from MerchantV1
 * @phpstan-import-type CreatePaymentParams from MerchantV1
 * @phpstan-import-type ZenyEvent from MerchantV1
 * @phpstan-import-type ListDisputesParams from MerchantV1
 */
final class TypedUsage
{
    /** @param Money $money */
    public static function value(array $money): string
    {
        return $money['value'];
    }

    /** @return CreatePaymentParams */
    public static function params(): array
    {
        return ['reference' => 'order-1', 'amount' => ['value' => '150000', 'currency' => 'IDR']];
    }

    /** @param ListDisputesParams $params */
    public static function limit(array $params): int
    {
        return $params['limit'] ?? 10;
    }

    /** @param ZenyEvent $event */
    public static function summary(array $event): string
    {
        return match ($event['type']) {
            'payment.paid', 'payment.expired' => $event['data']['object']['reference'],
            'payment_attempt.failed' => $event['data']['object']['id'],
            'payout.paid', 'payout.failed', 'payout.canceled', 'payout.rejected' => $event['data']['object']['reference'],
            'dispute.opened', 'dispute.closed' => $event['data']['object']['payment'],
        };
    }
}
