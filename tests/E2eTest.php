<?php

declare(strict_types=1);

use Pest\PendingCalls\TestCall;
use Zeny\Zeny;

// Talks to the real Sandbox, so it runs only when ZENY_E2E_KEY holds a Sandbox
// (sk_test_) key. A live key is refused: this suite creates payments.
function e2eKey(): ?string
{
    $key = getenv('ZENY_E2E_KEY');

    return is_string($key) && str_starts_with($key, 'sk_test_') ? $key : null;
}

function e2eClient(): Zeny
{
    return new Zeny(e2eKey());
}

/** @return array{reference: string, amount: array{value: string, currency: string}} */
function e2ePaymentParams(): array
{
    return ['reference' => 'e2e-'.bin2hex(random_bytes(8)), 'amount' => ['value' => '10000', 'currency' => 'IDR']];
}

/** Registers a test that is skipped unless a Sandbox key is set. */
function sandboxTest(string $description, Closure $body): void
{
    $call = test($description, $body);
    if ($call instanceof TestCall) {
        $call->skip(fn (): bool => e2eKey() === null, 'Set ZENY_E2E_KEY to a Sandbox secret key to run the Sandbox suite.');
    }
}

sandboxTest('a created payment can be retrieved', function (): void {
    $payment = e2eClient()->payments->create(e2ePaymentParams())->toArray();

    expect(e2eClient()->payments->retrieve($payment['id'])->toArray()['id'])->toBe($payment['id']);
});

sandboxTest('a QRIS attempt starts on a new payment', function (): void {
    $payment = e2eClient()->payments->create(e2ePaymentParams())->toArray();

    $attempt = e2eClient()->payments->createAttempt($payment['id'], ['instrument' => 'QRIS'])->toArray();

    expect($attempt['payment'])->toBe($payment['id']);
});

sandboxTest('a created payment link has an https url', function (): void {
    $link = e2eClient()->paymentLinks->create(['name' => 'E2E link', 'amount' => ['value' => '10000', 'currency' => 'IDR']])->toArray();

    expect($link['url'])->toStartWith('https://');
});
