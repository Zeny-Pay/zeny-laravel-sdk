<?php

declare(strict_types=1);

use Zeny\Domain\RequestOptions;
use Zeny\Infrastructure\Http\RequestSpec;
use Zeny\Infrastructure\Repositories\HttpDisputeRepository;
use Zeny\Infrastructure\Repositories\HttpPaymentLinkRepository;
use Zeny\Infrastructure\Repositories\HttpPaymentRepository;
use Zeny\Infrastructure\Repositories\HttpPayoutRepository;
use Zeny\Infrastructure\Repositories\HttpTestFundsRepository;
use Zeny\Tests\Support\FakeTransport;

/** @return array{string, string, array<string, string|int|null>, array<string, mixed>|null, string|null} */
function sent(RequestSpec $spec): array
{
    return [$spec->method, $spec->path, $spec->query, $spec->body, $spec->idempotencyKey];
}

$amount = ['value' => '150000', 'currency' => 'IDR'];
$key = new RequestOptions('k-1');
$link = ['name' => 'Order #1042', 'amount' => $amount, 'instruments' => ['QRIS'], 'successRedirectUrl' => 'https://shop.example/thanks'];
$payout = ['reference' => 'po-1', 'beneficiary' => 'ben_1', 'amount' => $amount];

/** @var array<string, array{Closure(FakeTransport): mixed, array{string, string, array<string, string|int|null>, array<string, mixed>|null, string|null}}> $cases */
$cases = [
    'payments.create' => [
        fn (FakeTransport $t) => (new HttpPaymentRepository($t))->create(['reference' => 'order-1', 'amount' => $amount], $key),
        ['POST', '/payments', [], ['reference' => 'order-1', 'amount' => $amount], 'k-1'],
    ],
    'payments.retrieve' => [
        fn (FakeTransport $t) => (new HttpPaymentRepository($t))->retrieve('pay_1'),
        ['GET', '/payments/pay_1', [], null, null],
    ],
    'payments.createAttempt' => [
        fn (FakeTransport $t) => (new HttpPaymentRepository($t))->createAttempt('pay_1', ['instrument' => 'qris'], $key),
        ['POST', '/payments/pay_1/attempts', [], ['instrument' => 'qris'], 'k-1'],
    ],
    'paymentLinks.create' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->create($link, $key),
        ['POST', '/payment-links', [], $link, 'k-1'],
    ],
    'paymentLinks.retrieve' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->retrieve('pl_1'),
        ['GET', '/payment-links/pl_1', [], null, null],
    ],
    'paymentLinks.list' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->list(['status' => 'active', 'createdFrom' => '2026-11-01T00:00:00Z', 'limit' => 5, 'cursor' => 'c1']),
        ['GET', '/payment-links', ['status' => 'active', 'createdFrom' => '2026-11-01T00:00:00Z', 'limit' => 5, 'cursor' => 'c1'], null, null],
    ],
    'paymentLinks.list without filters' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->list(),
        ['GET', '/payment-links', [], null, null],
    ],
    'paymentLinks.close' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->close('pl_1'),
        ['POST', '/payment-links/pl_1/close', [], [], null],
    ],
    'paymentLinks.reopen' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->reopen('pl_1', $key),
        ['POST', '/payment-links/pl_1/reopen', [], [], 'k-1'],
    ],
    'paymentLinks.cancel' => [
        fn (FakeTransport $t) => (new HttpPaymentLinkRepository($t))->cancel('pl_1'),
        ['POST', '/payment-links/pl_1/cancel', [], [], null],
    ],
    'payouts.create' => [
        fn (FakeTransport $t) => (new HttpPayoutRepository($t))->create($payout, $key),
        ['POST', '/payouts', [], $payout, 'k-1'],
    ],
    'payouts.retrieve' => [
        fn (FakeTransport $t) => (new HttpPayoutRepository($t))->retrieve('po_1'),
        ['GET', '/payouts/po_1', [], null, null],
    ],
    'payouts.cancel' => [
        fn (FakeTransport $t) => (new HttpPayoutRepository($t))->cancel('po_1'),
        ['POST', '/payouts/po_1/cancel', [], [], null],
    ],
    'disputes.list' => [
        fn (FakeTransport $t) => (new HttpDisputeRepository($t))->list(['status' => 'open', 'limit' => 10, 'cursor' => 'c1']),
        ['GET', '/disputes', ['status' => 'open', 'limit' => 10, 'cursor' => 'c1'], null, null],
    ],
    'disputes.list without filters' => [
        fn (FakeTransport $t) => (new HttpDisputeRepository($t))->list(),
        ['GET', '/disputes', [], null, null],
    ],
    'disputes.retrieve' => [
        fn (FakeTransport $t) => (new HttpDisputeRepository($t))->retrieve('dp_1'),
        ['GET', '/disputes/dp_1', [], null, null],
    ],
    'testFunds.create' => [
        fn (FakeTransport $t) => (new HttpTestFundsRepository($t))->create(['amount' => $amount], $key),
        ['POST', '/test-funds', [], ['amount' => $amount], 'k-1'],
    ],
];

foreach ($cases as $name => [$call, $expected]) {
    test("{$name} sends method, path, query, body and key", function () use ($call, $expected): void {
        $transport = new FakeTransport;
        $call($transport);

        expect(sent($transport->last()))->toBe($expected);
    });
}

test('a repository returns the transport result', function (): void {
    expect((new HttpPaymentRepository(new FakeTransport))->retrieve('pay_1')['id'])->toBe('obj_1');
});

test('an empty id throws before anything is sent', function (): void {
    $transport = new FakeTransport;

    expect(fn () => (new HttpPayoutRepository($transport))->cancel(''))->toThrow(InvalidArgumentException::class);
});
