<?php

declare(strict_types=1);

use Zeny\Domain\JsonShape;

/** @param  array<string, mixed>  $body */
function shaped(array $body): string
{
    return json_encode(JsonShape::encode($body), JSON_THROW_ON_ERROR);
}

test('an empty body is an object', function () {
    expect(shaped([]))->toBe('{}');
});

test('an empty metadata map is an object', function () {
    expect(shaped(['metadata' => []]))->toBe('{"metadata":{}}');
});

test('a metadata map with numeric keys stays an object', function () {
    expect(shaped(['metadata' => ['0' => 'a']]))->toBe('{"metadata":{"0":"a"}}');
});

test('an empty free-form object is an object', function () {
    expect(shaped(['method' => ['type' => 'card', 'card' => []]]))->toBe('{"method":{"type":"card","card":{}}}');
});

test('a map inside a list item is an object', function () {
    expect(shaped(['data' => [['metadata' => []]]]))->toBe('{"data":[{"metadata":{}}]}');
});

test('an empty list stays a list', function () {
    expect(shaped(['instruments' => []]))->toBe('{"instruments":[]}');
});

test('a null map stays null', function () {
    expect(shaped(['method' => ['card' => null]]))->toBe('{"method":{"card":null}}');
});
