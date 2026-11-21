<?php

declare(strict_types=1);

use Zeny\Domain\IdempotencyKey;

test('keeps a valid key', function () {
    expect(IdempotencyKey::parse('order-1:create')->value)->toBe('order-1:create');
});

test('casts to the key', function () {
    expect((string) IdempotencyKey::parse('order-1:create'))->toBe('order-1:create');
});

test('accepts one character', function () {
    expect(IdempotencyKey::parse('a')->value)->toBe('a');
});

test('accepts 255 characters', function () {
    expect(strlen(IdempotencyKey::parse(str_repeat('a', 255))->value))->toBe(255);
});

$rejected = ['empty' => '', '256 chars' => str_repeat('a', 256), 'newline' => "a\nb", 'non-ascii' => "\u{e9}", 'null' => null, 'number' => 5];
foreach ($rejected as $name => $raw) {
    test("rejects {$name}", function () use ($raw) {
        expect(fn () => IdempotencyKey::parse($raw))
            ->toThrow(InvalidArgumentException::class, 'idempotencyKey must be 1-255 printable ASCII characters');
    });
}
