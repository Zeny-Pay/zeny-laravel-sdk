<?php

declare(strict_types=1);

use Zeny\Scripts\Json;
use Zeny\Scripts\PhpStanTypeWriter;
use Zeny\Scripts\SchemaTyper;

function generatedFromContract(): string
{
    return (new PhpStanTypeWriter(Json::decodeFile(dirname(__DIR__, 2).'/openapi/merchant.v1.json')))->write();
}

test('the committed types match the contract', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/src/Generated/MerchantV1.php'))->toBe(generatedFromContract());
});

test('Money is a decimal-string value with a currency', function () {
    expect(generatedFromContract())->toContain(<<<'TYPE'
     * @phpstan-type S_type_v1_Money array{
     *     value: string,
     *     currency: string,
     * }
    TYPE);
});

test('an open enum stays string so new values are not type errors', function () {
    $type = (new SchemaTyper)->type(['anyOf' => [['type' => 'string', 'enum' => ['a', 'b']], ['type' => 'string']]]);

    expect($type)->toBe('string');
});

test('a closed enum becomes a union of literals', function () {
    expect((new SchemaTyper)->type(['type' => 'string', 'enum' => ['open', 'won']]))->toBe("'open'|'won'");
});

test('a nullable reference becomes the alias or null', function () {
    $type = (new SchemaTyper)->type(['anyOf' => [['$ref' => '#/components/schemas/type.v1.Money'], ['type' => 'null']]]);

    expect($type)->toBe('S_type_v1_Money|null');
});

test('a key outside the required list is optional', function () {
    expect((new SchemaTyper)->type(['type' => 'object', 'properties' => ['a' => ['type' => 'integer']]]))
        ->toBe("array{\n    a?: int,\n}");
});

test('a key that is not an identifier is quoted', function () {
    expect((new SchemaTyper)->shape(['a-b' => ['type' => 'boolean']], ['a-b'], 0))->toBe("array{\n    'a-b': bool,\n}");
});

test('a string map keeps its value type', function () {
    expect((new SchemaTyper)->type(['type' => 'object', 'additionalProperties' => ['type' => 'string']]))
        ->toBe('array<string, string>');
});

test('an array becomes a list of its items', function () {
    expect((new SchemaTyper)->type(['type' => 'array', 'items' => ['type' => 'number']]))->toBe('list<float|int>');
});

test('a type list becomes a union', function () {
    expect((new SchemaTyper)->type(['type' => ['string', 'null']]))->toBe('string|null');
});

test('an unknown schema type fails loudly', function () {
    expect(fn () => (new SchemaTyper)->type(['type' => 'file']))
        ->toThrow(RuntimeException::class, 'unknown schema type file');
});

test('a contract without a public schema fails loudly', function () {
    expect(fn () => (new PhpStanTypeWriter(['components' => ['schemas' => []]]))->write())
        ->toThrow(RuntimeException::class, 'contract has no schema');
});
