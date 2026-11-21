<?php

declare(strict_types=1);

namespace Zeny\Laravel;

use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\Repositories\PaymentLinkRepository;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\Repositories\TestFundsRepository;

/**
 * What the facade forwards to. It is an interface so `Zeny::shouldReceive()`
 * can mock it: the client itself is final and exposes its repositories as
 * properties, which neither a facade nor a mock can forward.
 */
interface ZenyApi
{
    public function paymentLinks(): PaymentLinkRepository;

    public function payments(): PaymentRepository;

    public function payouts(): PayoutRepository;

    public function disputes(): DisputeRepository;

    public function testFunds(): TestFundsRepository;
}
