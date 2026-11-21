<?php

declare(strict_types=1);

use Zeny\Infrastructure\Repositories\Routes;

test('fill puts each id into its slot in order', function (): void {
    expect(Routes::fill('/a/{id}/b/{id}', 'x', 'y'))->toBe('/a/x/b/y');
});

test('fill URL-encodes ids so they cannot add path segments', function (): void {
    expect(Routes::fill('/payments/{id}', '../pay 1?x=#'))->toBe('/payments/..%2Fpay%201%3Fx%3D%23');
});

test('fill rejects an empty id', function (): void {
    expect(fn () => Routes::fill('/payments/{id}', ''))->toThrow(InvalidArgumentException::class, 'id must be a non-empty string');
});

test('fill rejects a non-string id', function (): void {
    expect(fn () => Routes::fill('/payments/{id}', 42))->toThrow(InvalidArgumentException::class, 'id must be a non-empty string');
});

test('fill reports a slot/id count mismatch before validating any id', function (): void {
    expect(fn () => Routes::fill('/payments/{id}', '', ''))->toThrow(InvalidArgumentException::class, 'expected 1 id(s) for /payments/{id}, got 2');
});

test('fill with no slots and no ids returns the template', function (): void {
    expect(Routes::fill('/payments'))->toBe('/payments');
});

$routes = [
    'payment' => [fn (): string => Routes::payment('pay_1'), '/payments/pay_1'],
    'paymentAttempts' => [fn (): string => Routes::paymentAttempts('pay_1'), '/payments/pay_1/attempts'],
    'payout' => [fn (): string => Routes::payout('po_1'), '/payouts/po_1'],
    'payoutCancel' => [fn (): string => Routes::payoutCancel('po_1'), '/payouts/po_1/cancel'],
    'dispute' => [fn (): string => Routes::dispute('dp_1'), '/disputes/dp_1'],
];
foreach ($routes as $name => [$build, $path]) {
    test("route {$name} is {$path}", function () use ($build, $path): void {
        expect($build())->toBe($path);
    });
}
