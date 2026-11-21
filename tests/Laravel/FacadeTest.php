<?php

declare(strict_types=1);

namespace Zeny\Tests\Laravel;

use Mockery;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Infrastructure\Webhooks;
use Zeny\Laravel\ClientApi;
use Zeny\Laravel\Facades\Zeny as ZenyFacade;
use Zeny\Zeny;

final class FacadeTest extends LaravelTestCase
{
    public function test_payment_links_returns_the_container_clients_repository(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class)->paymentLinks, ZenyFacade::paymentLinks());
    }

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

    public function test_should_receive_mocks_a_forwarded_call(): void
    {
        $payments = Mockery::mock(PaymentRepository::class);
        ZenyFacade::shouldReceive('payments')->once()->andReturn($payments);

        $this->assertSame($payments, ZenyFacade::payments());
    }

    public function test_should_receive_needs_no_key(): void
    {
        config(['zeny.secret_key' => null]);
        $payments = Mockery::mock(PaymentRepository::class);
        ZenyFacade::shouldReceive('payments')->andReturn($payments);

        $this->assertSame($payments, ZenyFacade::payments());
    }

    public function test_swap_replaces_the_root(): void
    {
        $fake = new ClientApi(new Zeny('sk_test_swapped'));
        ZenyFacade::swap($fake);

        $this->assertSame($fake->payments(), ZenyFacade::payments());
    }
}
