<?php

declare(strict_types=1);

use Zeny\Scripts\ObjectFields;

$map = ['type' => 'object', 'additionalProperties' => ['type' => 'string']];
$struct = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];

test('a map property is listed', function () use ($map): void {
    expect((new ObjectFields(['A' => ['properties' => ['metadata' => $map]]]))->names())->toBe(['metadata']);
});

test('a nullable map (anyOf with null) is listed', function () use ($map): void {
    $schemas = ['A' => ['properties' => ['metadata' => ['anyOf' => [$map, ['type' => 'null']]]]]];

    expect((new ObjectFields($schemas))->names())->toBe(['metadata']);
});

test('a $ref to a free-form object is listed', function (): void {
    $schemas = ['A' => ['properties' => ['card' => ['$ref' => '#/components/schemas/Card']]], 'Card' => ['type' => 'object']];

    expect((new ObjectFields($schemas))->names())->toBe(['card']);
});

test('an object with declared properties is not listed', function () use ($struct): void {
    expect((new ObjectFields(['A' => ['properties' => ['amount' => $struct]]]))->names())->toBe([]);
});

test('a name that is a map in one schema and not in another throws', function () use ($map): void {
    $schemas = ['A' => ['properties' => ['data' => $map]], 'B' => ['properties' => ['data' => ['type' => 'string']]]];

    expect(fn () => (new ObjectFields($schemas))->names())->toThrow(RuntimeException::class, 'property data is a map');
});
