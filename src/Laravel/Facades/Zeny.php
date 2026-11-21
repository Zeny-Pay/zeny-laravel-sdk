<?php

declare(strict_types=1);

namespace Zeny\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\Repositories\TestFundsRepository;
use Zeny\Infrastructure\Webhooks;
use Zeny\Zeny as Client;

/**
 * Static access to the container's client: `Zeny::payments()->create(...)`.
 * The client exposes repositories as properties, which a facade cannot
 * forward, so each one has a method here.
 */
final class Zeny extends Facade
{
    public static function payments(): PaymentRepository
    {
        return self::client()->payments;
    }

    public static function payouts(): PayoutRepository
    {
        return self::client()->payouts;
    }

    public static function disputes(): DisputeRepository
    {
        return self::client()->disputes;
    }

    public static function testFunds(): TestFundsRepository
    {
        return self::client()->testFunds;
    }

    public static function webhooks(): Webhooks
    {
        return Client::webhooks();
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }

    private static function client(): Client
    {
        $client = self::getFacadeRoot();
        if (! $client instanceof Client) {
            throw new \LogicException('The container does not hold the API client; is the service provider registered?');
        }

        return $client;
    }
}
