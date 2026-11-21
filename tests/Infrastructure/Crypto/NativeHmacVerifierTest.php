<?php

declare(strict_types=1);

use Zeny\Infrastructure\Crypto\NativeHmacVerifier;

test('the native verifier accepts the backend golden vector', function (): void {
    $signature = (string) hex2bin('f5dd82795177768db12e8bc75c61f78f34b289461ed7966a038c48b9fa972f4e');

    expect((new NativeHmacVerifier)->verify('whsec_test', '1792552500.{"a":1}', $signature))->toBeTrue();
});

test('the native verifier rejects a different message', function (): void {
    $signature = (string) hex2bin('f5dd82795177768db12e8bc75c61f78f34b289461ed7966a038c48b9fa972f4e');

    expect((new NativeHmacVerifier)->verify('whsec_test', '1792552500.{"a":2}', $signature))->toBeFalse();
});
