<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Zeny\Zeny;

const ZENY_TEST_KEY = 'sk_test_zenyclientmarker42';

function dumped(mixed $value): string
{
    ob_start();
    var_dump($value);

    return (string) ob_get_clean();
}

test('Zeny exposes the five repositories', function (): void {
    $zeny = new Zeny(ZENY_TEST_KEY);

    expect(array_keys(get_object_vars($zeny)))->toBe(['paymentLinks', 'payments', 'payouts', 'disputes', 'testFunds']);
});

test('Zeny stores no config or transport property', function (): void {
    $names = array_map(fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(Zeny::class))->getProperties());

    expect($names)->toBe(['paymentLinks', 'payments', 'payouts', 'disputes', 'testFunds']);
});

test('var_dump of a client prints no key, not even a redacted one', function (): void {
    expect(dumped(new Zeny(ZENY_TEST_KEY)))->not->toContain('sk_');
});

test('print_r of a client prints no key', function (): void {
    expect(print_r(new Zeny(ZENY_TEST_KEY), true))->not->toContain('sk_');
});

test('a malformed key throws InvalidArgumentException', function (): void {
    expect(fn () => new Zeny(null))->toThrow(InvalidArgumentException::class);
});

test('unusable options throw InvalidArgumentException', function (): void {
    expect(fn () => new Zeny(ZENY_TEST_KEY, ['maxRetries' => -1]))->toThrow(InvalidArgumentException::class);
});

test('a call goes through the configured client with the bearer key', function (): void {
    $requests = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"id":"pay_1"}')]));
    $stack->push(Middleware::tap(function (RequestInterface $request) use (&$requests): void {
        $requests[] = $request;
    }));
    $zeny = new Zeny(ZENY_TEST_KEY, ['httpClient' => new Client(['handler' => $stack])]);
    $zeny->payments->retrieve('pay_1');

    expect([(string) $requests[0]->getUri(), $requests[0]->getHeaderLine('Authorization')])
        ->toBe(['https://snorlax.usezeny.com/api/merchant/v1/payments/pay_1', 'Bearer '.ZENY_TEST_KEY]);
});
