<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use InvalidArgumentException;
use Zeny\Domain\ResourceId;

/** Every route, relative to `/api/merchant/v1`. */
final class Routes
{
    public const PAYMENTS = '/payments';

    public const PAYOUTS = '/payouts';

    public const DISPUTES = '/disputes';

    public const TEST_FUNDS = '/test-funds';

    public const PAYMENT_LINKS = '/payment-links';

    /** Fills each `{id}` slot of `$template` in order with a validated, URL-encoded id. */
    public static function fill(string $template, mixed ...$ids): string
    {
        // Counted before any id is checked, so a slot/id mismatch fails without validating ids.
        $slots = substr_count($template, '{id}');
        if ($slots !== count($ids)) {
            throw new InvalidArgumentException("expected {$slots} id(s) for {$template}, got ".count($ids));
        }
        $encoded = array_map(fn (mixed $id): string => rawurlencode(ResourceId::parse($id)->value), array_values($ids));
        $parts = explode('{id}', $template);
        $path = array_shift($parts);
        foreach ($parts as $index => $part) {
            $path .= $encoded[$index].$part;
        }

        return $path;
    }

    public static function payment(string $id): string
    {
        return self::fill('/payments/{id}', $id);
    }

    public static function paymentAttempts(string $id): string
    {
        return self::fill('/payments/{id}/attempts', $id);
    }

    public static function payout(string $id): string
    {
        return self::fill('/payouts/{id}', $id);
    }

    public static function payoutCancel(string $id): string
    {
        return self::fill('/payouts/{id}/cancel', $id);
    }

    public static function dispute(string $id): string
    {
        return self::fill('/disputes/{id}', $id);
    }

    public static function paymentLink(string $id): string
    {
        return self::fill('/payment-links/{id}', $id);
    }

    /** @param 'close'|'reopen'|'cancel' $action */
    public static function paymentLinkAction(string $id, string $action): string
    {
        return self::fill('/payment-links/{id}/'.$action, $id);
    }
}
