<?php

declare(strict_types=1);

use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Domain\Webhook\HmacVerifier;
use Zeny\Domain\Webhook\Webhook;
use Zeny\Infrastructure\Webhooks;
use Zeny\Tests\Support\WebhookFixture as F;
use Zeny\Zeny;

test('a correctly signed body returns the event', function (): void {
    expect(F::run(F::body(), F::headers()))->toBe(F::EVENT);
});

test('the backend golden vector verifies', function (): void {
    // From apps/backend/internal/domain/notify/request_test.go (TestSign), as in zeny-node.
    $headers = ['Webhook-Signature' => 'sha256=f5dd82795177768db12e8bc75c61f78f34b289461ed7966a038c48b9fa972f4e', 'Webhook-Timestamp' => '1792552500'];

    expect(Zeny::webhooks()->constructEvent('{"a":1}', $headers, 'whsec_test', ['now' => fn (): int => 1792552500000]))->toBe(['a' => 1]);
});

test('Zeny::webhooks() verifies against the system clock', function (): void {
    $now = time();

    expect(Zeny::webhooks()->constructEvent(F::body(), F::headers($now), F::SECRET)['id'])->toBe('evt_1');
});

test('one changed byte is a signature_mismatch', function (): void {
    expect(F::code(str_replace('pay_1', 'pay_2', F::body()), F::headers()))->toBe('signature_mismatch');
});

test('a wrong secret is a signature_mismatch', function (): void {
    expect(F::code(F::body(), F::headers(), secret: 'whsec_other'))->toBe('signature_mismatch');
});

test('the timestamp is part of the signed data', function (): void {
    expect(F::code(F::body(), ['webhook-timestamp' => (string) (F::NOW + 1)] + F::headers()))->toBe('signature_mismatch');
});

$hex = substr(F::sign(F::NOW, F::body()), 7);
$malformed = [
    'no prefix' => $hex,
    '63 hex characters' => 'sha256='.substr($hex, 1),
    '65 hex characters' => 'sha256='.$hex.'0',
    'non-hex' => 'sha256='.str_repeat('g', 64),
    'empty hex' => 'sha256=',
    'another algorithm' => 'sha1='.$hex,
    'trailing newline' => 'sha256='.$hex."\n",
];
foreach ($malformed as $name => $signature) {
    test("a signature with {$name} is signature_malformed", function () use ($signature): void {
        expect(F::code(F::body(), ['webhook-signature' => $signature] + F::headers()))->toBe('signature_malformed');
    });
}

test('uppercase hex is accepted', function () use ($hex): void {
    expect(F::run(F::body(), ['webhook-signature' => 'sha256='.strtoupper($hex)] + F::headers())['id'])->toBe('evt_1');
});

foreach ([F::NOW - 300, F::NOW + 300] as $timestamp) {
    test("timestamp {$timestamp} is inside the 300 s window", function () use ($timestamp): void {
        expect(F::run(F::body(), F::headers($timestamp))['id'])->toBe('evt_1');
    });
}

foreach (['old' => F::NOW - 301, 'future' => F::NOW + 301] as $name => $timestamp) {
    test("an {$name} timestamp is timestamp_out_of_tolerance", function () use ($timestamp): void {
        expect(F::code(F::body(), F::headers($timestamp)))->toBe('timestamp_out_of_tolerance');
    });
}

test('toleranceSec widens the window', function (): void {
    expect(F::run(F::body(), F::headers(F::NOW - 600), ['toleranceSec' => 600])['id'])->toBe('evt_1');
});

foreach (['abc', '-5', '1.5', '1e9', ' 1800000000', '0x10', "1800000000\n"] as $timestamp) {
    test('timestamp '.json_encode($timestamp).' is not digits only', function () use ($timestamp): void {
        expect(F::code(F::body(), F::headers($timestamp)))->toBe('timestamp_out_of_tolerance');
    });
}

test('the timestamp is checked before the signature', function (): void {
    expect(F::code(F::body(), ['webhook-signature' => 'garbage', 'webhook-timestamp' => (string) (F::NOW - 1000)]))->toBe('timestamp_out_of_tolerance');
});

test('a non-finite clock fails closed', function (): void {
    expect(F::code(F::body(), F::headers(), ['now' => fn (): float => NAN]))->toBe('timestamp_out_of_tolerance');
});

test('a clock returning a non-number fails closed', function (): void {
    expect(F::code(F::body(), F::headers(), ['now' => fn (): string => 'soon']))->toBe('timestamp_out_of_tolerance');
});

foreach (['array' => F::EVENT, 'null' => null, 'int' => 5] as $name => $body) {
    test("a parsed {$name} body is signature_malformed with a raw-body hint", function () use ($body): void {
        expect(F::caught($body, F::headers())->getMessage())->toContain('raw request body');
    });
}

test('header names are case-insensitive', function (): void {
    $headers = F::headers();

    expect(F::run(F::body(), ['Webhook-Signature' => $headers['webhook-signature'], 'WEBHOOK-TIMESTAMP' => $headers['webhook-timestamp']])['id'])->toBe('evt_1');
});

test('for a list header value the first element wins', function (): void {
    $headers = F::headers();

    expect(F::run(F::body(), ['webhook-signature' => [$headers['webhook-signature'], 'x'], 'webhook-timestamp' => [$headers['webhook-timestamp']]])['id'])->toBe('evt_1');
});

$timestampOnly = ['webhook-timestamp' => (string) F::NOW];
$missing = [
    'no headers' => [],
    'no signature' => $timestampOnly,
    'no timestamp' => ['webhook-signature' => F::headers()['webhook-signature']],
    'an empty signature' => ['webhook-signature' => ''] + $timestampOnly,
    'an empty list' => ['webhook-signature' => []] + $timestampOnly,
    'a null signature' => ['webhook-signature' => null] + $timestampOnly,
    'a numeric signature' => ['webhook-signature' => 5] + $timestampOnly,
    'a list of a number' => ['webhook-signature' => [5]] + $timestampOnly,
    'headers that are not an array' => null,
];
foreach ($missing as $name => $headers) {
    test("{$name} is signature_missing", function () use ($headers): void {
        expect(F::code(F::body(), $headers))->toBe('signature_missing');
    });
}

foreach (['not json', '[1]', '[]', 'null', '5', '"s"', "{\xff}"] as $payload) {
    test('a signed body of '.json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE).' is invalid_payload', function () use ($payload): void {
        expect(F::code($payload, F::headers(F::NOW, $payload)))->toBe('invalid_payload');
    });
}

test('an empty JSON object is a valid payload', function (): void {
    expect(F::run('{}', F::headers(F::NOW, '{}')))->toBe([]);
});

test('an unknown event type passes through', function (): void {
    $payload = (string) json_encode(['type' => 'future.thing'] + F::EVENT);

    expect(F::run($payload, F::headers(F::NOW, $payload))['type'])->toBe('future.thing');
});

foreach (['empty' => '', 'null' => null, 'int' => 5] as $name => $secret) {
    test("an {$name} secret throws InvalidArgumentException", function () use ($secret): void {
        expect(fn () => F::run(F::body(), F::headers(), secret: $secret))->toThrow(InvalidArgumentException::class, 'webhook secret');
    });
}

foreach (['negative' => -1, 'NAN' => NAN, 'INF' => INF, 'a string' => '300'] as $name => $tolerance) {
    test("a {$name} toleranceSec throws InvalidArgumentException", function () use ($tolerance): void {
        expect(fn () => F::run(F::body(), F::headers(), ['toleranceSec' => $tolerance]))->toThrow(InvalidArgumentException::class, 'toleranceSec');
    });
}

test('a now option that is not a Closure throws InvalidArgumentException', function (): void {
    expect(fn () => (new Webhooks)->constructEvent(F::body(), F::headers(), F::SECRET, ['now' => 5]))->toThrow(InvalidArgumentException::class, 'now');
});

test('every failure has the signature_verification type', function (): void {
    expect(F::caught(F::body(), [])->type)->toBe('signature_verification');
});

$leaks = [
    'mismatch' => [F::body(), ['webhook-signature' => 'sha256='.str_repeat('0', 64)] + F::headers()],
    'malformed' => [F::body(), ['webhook-signature' => 'zzz'] + F::headers()],
    'old timestamp' => [F::body(), F::headers(F::NOW - 1000)],
    'parsed body' => [F::EVENT, F::headers()],
];
foreach ($leaks as $name => [$body, $headers]) {
    test("a {$name} error never prints the secret, signature or body", function () use ($body, $headers): void {
        $error = F::caught($body, $headers);
        $text = $error->getMessage().json_encode($error).print_r(get_object_vars($error), true);

        expect(str_contains($text, F::SECRET) || str_contains($text, F::headers()['webhook-signature']) || str_contains($text, 'pay_1'))->toBeFalse();
    });
}

test('SDK stack frames redact the webhook secret', function (): void {
    $sdkArgs = array_map(
        fn (array $frame): string => print_r($frame['args'] ?? [], true),
        array_filter(F::caught(F::body(), F::headers(), secret: 'whsec_other')->getTrace(), fn (array $frame): bool => str_starts_with($frame['class'] ?? '', 'Zeny\\') && ! str_starts_with($frame['class'] ?? '', 'Zeny\\Tests')),
    );

    expect(implode('', $sdkArgs))->not->toContain('whsec_other');
});

test('the verifier receives "{timestamp}." plus the raw body and the 32 signature bytes', function (): void {
    $verifier = new class implements HmacVerifier
    {
        /** @var list<string> */
        public array $seen = [];

        public function verify(#[SensitiveParameter] string $secret, string $message, string $signature): bool
        {
            $this->seen = [$secret, $message, bin2hex($signature)];

            return true;
        }
    };
    Webhook::constructEventWith($verifier, fn (): int => F::NOW * 1000, '{"a":1}', F::headers(F::NOW, '{"a":1}'), 'whsec_x');

    expect($verifier->seen)->toBe(['whsec_x', F::NOW.'.{"a":1}', substr(F::sign(F::NOW, '{"a":1}'), 7)]);
});

test('a failed verification is a signature_mismatch whatever the bytes', function (): void {
    $refuse = new class implements HmacVerifier
    {
        public function verify(#[SensitiveParameter] string $secret, string $message, string $signature): bool
        {
            return false;
        }
    };

    expect(fn () => Webhook::constructEventWith($refuse, fn (): int => F::NOW * 1000, F::body(), F::headers(), F::SECRET))
        ->toThrow(SignatureVerificationError::class, 'does not match');
});
