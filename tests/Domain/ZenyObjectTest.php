<?php

declare(strict_types=1);

use Zeny\Domain\ResponseMeta;
use Zeny\Domain\ZenyObject;

/**
 * @param  array<string, mixed>  $body
 * @return ZenyObject<array<string, mixed>>
 */
function payment(array $body = ['id' => 'pay_1', 'amount' => ['value' => '150000', 'currency' => 'IDR']]): ZenyObject
{
    return new ZenyObject($body, new ResponseMeta(201, 'req_1', false, 'idem-1', ['x-request-id' => ['req_1']]));
}

test('reads a field by key', function () {
    expect(payment()['id'])->toBe('pay_1');
});

test('reads a nested field', function () {
    $amount = payment()['amount'];

    expect(is_array($amount) ? $amount['value'] : null)->toBe('150000');
});

test('a missing key reads as null', function () {
    expect(payment()['nope'])->toBeNull();
});

test('isset reports a present key', function () {
    expect(isset(payment()['id']))->toBeTrue();
});

test('writing a key throws', function () {
    expect(function () {
        $object = payment();
        $object['id'] = 'x';
    })->toThrow(LogicException::class);
});

test('unsetting a key throws', function () {
    expect(function () {
        $object = payment();
        unset($object['id']);
    })->toThrow(LogicException::class);
});

test('toArray returns the body without lastResponse', function () {
    expect(payment()->toArray())->toBe(['id' => 'pay_1', 'amount' => ['value' => '150000', 'currency' => 'IDR']]);
});

test('json_encode returns the body only', function () {
    expect(json_encode(payment()))->toBe('{"id":"pay_1","amount":{"value":"150000","currency":"IDR"}}');
});

test('an empty body encodes as an object', function () {
    expect(json_encode(payment([])))->toBe('{}');
});

test('iterates over the body', function () {
    expect(array_keys(iterator_to_array(payment())))->toBe(['id', 'amount']);
});

test('lastResponse carries the response facts', function () {
    expect(payment()->lastResponse->requestId)->toBe('req_1');
});
