<?php

declare(strict_types=1);

namespace Zeny\Tests\Laravel;

use Zeny\Infrastructure\Webhooks;
use Zeny\Laravel\Facades\Zeny as ZenyFacade;
use Zeny\Zeny;

final class FacadeTest extends LaravelTestCase
{
    public function test_payments_returns_the_container_clients_repository(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class)->payments, ZenyFacade::payments());
    }

    public function test_payouts_returns_the_container_clients_repository(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class)->payouts, ZenyFacade::payouts());
    }

    public function test_disputes_returns_the_container_clients_repository(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class)->disputes, ZenyFacade::disputes());
    }

    public function test_test_funds_returns_the_container_clients_repository(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class)->testFunds, ZenyFacade::testFunds());
    }

    public function test_webhooks_returns_the_webhook_helper(): void
    {
        $this->assertInstanceOf(Webhooks::class, ZenyFacade::webhooks());
    }
}
