<?php

declare(strict_types=1);

use Zeny\Domain\ResourceId;

test('keeps a non-empty id', function () {
    expect(ResourceId::parse('pay_1')->value)->toBe('pay_1');
});

test('does not check the prefix', function () {
    expect((string) ResourceId::parse('anything'))->toBe('anything');
});

foreach (['empty' => '', 'null' => null, 'number' => 5] as $name => $raw) {
    test("rejects {$name}", function () use ($raw) {
        expect(fn () => ResourceId::parse($raw))->toThrow(InvalidArgumentException::class, 'id must be a non-empty string');
    });
}
