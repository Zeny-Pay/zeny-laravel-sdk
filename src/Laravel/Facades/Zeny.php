<?php

declare(strict_types=1);

namespace Zeny\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\Repositories\PaymentLinkRepository;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\Repositories\TestFundsRepository;
use Zeny\Infrastructure\Webhooks;
use Zeny\Laravel\ZenyApi;
use Zeny\Zeny as Client;

/**
 * Static access to the container's client: `Zeny::payments()->create(...)`.
 * Calls forward to the ZenyApi binding, so `Zeny::shouldReceive('payments')`
 * and `Zeny::swap($fake)` work in tests without a key.
 *
 * @method static PaymentLinkRepository paymentLinks()
 * @method static PaymentRepository payments()
 * @method static PayoutRepository payouts()
 * @method static DisputeRepository disputes()
 * @method static TestFundsRepository testFunds()
 */
final class Zeny extends Facade
{
    /** Webhook helpers need no client, so this one is not forwarded (and not mocked). */
    public static function webhooks(): Webhooks
    {
        return Client::webhooks();
    }

    protected static function getFacadeAccessor(): string
    {
        return ZenyApi::class;
    }

    /** Mock the interface, never the final client, and without resolving it (which needs a key). */
    protected static function getMockableClass(): string
    {
        return ZenyApi::class;
    }
}
