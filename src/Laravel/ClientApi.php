<?php

declare(strict_types=1);

namespace Zeny\Laravel;

use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\Repositories\PaymentLinkRepository;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\Repositories\TestFundsRepository;
use Zeny\Zeny;

/** The facade's root in an app: the container's client, its repositories as methods. */
final class ClientApi implements ZenyApi
{
    public function __construct(private readonly Zeny $client) {}

    public function paymentLinks(): PaymentLinkRepository
    {
        return $this->client->paymentLinks;
    }

    public function payments(): PaymentRepository
    {
        return $this->client->payments;
    }

    public function payouts(): PayoutRepository
    {
        return $this->client->payouts;
    }

    public function disputes(): DisputeRepository
    {
        return $this->client->disputes;
    }

    public function testFunds(): TestFundsRepository
    {
        return $this->client->testFunds;
    }
}
