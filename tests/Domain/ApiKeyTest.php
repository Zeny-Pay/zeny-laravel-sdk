<?php

declare(strict_types=1);

use Zeny\Domain\ApiKey;

const KEY_MESSAGE = 'apiKey must be a secret key starting with "sk_live_" or "sk_test_"';

test('sk_live_ is live', function () {
    expect(ApiKey::parse('sk_live_x')->mode)->toBe('live');
});

test('sk_test_ is test', function () {
    expect(ApiKey::parse('sk_test_x')->mode)->toBe('test');
});

test('value returns the secret', function () {
    expect(ApiKey::parse('sk_test_abc')->value())->toBe('sk_test_abc');
});

$printers = [
    'string cast' => fn (ApiKey $key): string => (string) $key,
    'var_dump' => function (ApiKey $key): string {
        ob_start();
        var_dump($key);

        return (string) ob_get_clean();
    },
    'print_r' => fn (ApiKey $key): string => print_r($key, true),
    'json_encode' => fn (ApiKey $key): string => (string) json_encode($key),
];
foreach ($printers as $name => $print) {
    test("{$name} never prints the secret", function () use ($print) {
        expect($print(ApiKey::parse('sk_test_SECRETPART')))->not->toContain('SECRETPART');
    });
}

test('the string form names the mode', function () {
    expect((string) ApiKey::parse('sk_live_SECRET'))->toBe('ApiKey(sk_live_***)');
});

test('serialize is refused', function () {
    expect(fn () => serialize(ApiKey::parse('sk_test_SECRET')))->toThrow(LogicException::class);
});

$rejected = [
    'empty string' => '',
    'publishable key' => 'pk_live_x',
    'prefix only' => 'sk_live_',
    'space' => 'sk_test_ab cd',
    'newline' => "sk_test_SECRET\nX",
    'non-ascii' => "sk_test_\u{e9}",
    'null' => null,
    'number' => 5,
];
foreach ($rejected as $name => $raw) {
    test("rejects {$name} with a fixed message", function () use ($raw) {
        expect(fn () => ApiKey::parse($raw))->toThrow(InvalidArgumentException::class, KEY_MESSAGE);
    });
}

test("the rejection's trace does not carry the input", function () {
    try {
        ApiKey::parse("sk_test_TRACEMARK\nX");
    } catch (InvalidArgumentException $e) {
        expect(print_r($e, true))->not->toContain('TRACEMARK');
    }
});
