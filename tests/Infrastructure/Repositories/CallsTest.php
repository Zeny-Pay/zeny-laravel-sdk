<?php

declare(strict_types=1);

use Zeny\Domain\RequestOptions;
use Zeny\Infrastructure\Repositories\Calls;
use Zeny\Tests\Support\FakeTransport;

test('get sends a GET with the query', function (): void {
    $transport = new FakeTransport;
    Calls::get($transport, '/disputes', ['limit' => 5]);

    expect([$transport->last()->method, $transport->last()->path, $transport->last()->query])->toBe(['GET', '/disputes', ['limit' => 5]]);
});

test('post forwards the caller idempotency key', function (): void {
    $transport = new FakeTransport;
    Calls::post($transport, '/payments', ['a' => 1], new RequestOptions('order-1'));

    expect($transport->last()->idempotencyKey)->toBe('order-1');
});

test('post leaves the idempotency key to the transport when none is given', function (): void {
    $transport = new FakeTransport;
    Calls::post($transport, '/payments', ['a' => 1]);

    expect($transport->last()->idempotencyKey)->toBeNull();
});

test('post leaves the idempotency key to the transport when options carry none', function (): void {
    $transport = new FakeTransport;
    Calls::post($transport, '/payments', ['a' => 1], new RequestOptions);

    expect($transport->last()->idempotencyKey)->toBeNull();
});

test('post sends the body unchanged', function (): void {
    $transport = new FakeTransport;
    Calls::post($transport, '/payments', ['a' => 1]);

    expect($transport->last()->body)->toBe(['a' => 1]);
});
