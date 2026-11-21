<?php

declare(strict_types=1);

namespace Zeny;

use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\Repositories\TestFundsRepository;
use Zeny\Infrastructure\Config;
use Zeny\Infrastructure\Http\Psr18Transport;
use Zeny\Infrastructure\Repositories\HttpDisputeRepository;
use Zeny\Infrastructure\Repositories\HttpPaymentRepository;
use Zeny\Infrastructure\Repositories\HttpPayoutRepository;
use Zeny\Infrastructure\Repositories\HttpTestFundsRepository;
use Zeny\Infrastructure\Webhooks;

/**
 * The Merchant API client. The key prefix (`sk_test_` or `sk_live_`) picks
 * Sandbox or Production. One client is cheap and safe to share.
 *
 *     $zeny = new Zeny(getenv('ZENY_SECRET_KEY'));
 *     $payment = $zeny->payments->create([
 *         'reference' => 'order-1042',
 *         'amount' => ['value' => '150000', 'currency' => 'IDR'],
 *     ]);
 */
final class Zeny
{
    /** Create and read payments and their attempts. */
    public readonly PaymentRepository $payments;

    /** Create, read and cancel payouts. */
    public readonly PayoutRepository $payouts;

    /** Read disputes. */
    public readonly DisputeRepository $disputes;

    /** Credit test funds. Sandbox only. */
    public readonly TestFundsRepository $testFunds;

    /** Webhook helpers that need no API key, so a receiver can verify without a client. */
    public static function webhooks(): Webhooks
    {
        return new Webhooks;
    }

    /**
     * @param  mixed  $apiKey  a secret key starting with `sk_test_` or `sk_live_`; anything else throws
     * @param  array<string, mixed>  $options  `baseUrl`, `maxRetries`, `timeoutMs`, `httpClient`
     *
     * @throws \InvalidArgumentException for a malformed key or unusable options
     */
    public function __construct(#[\SensitiveParameter] mixed $apiKey, array $options = [])
    {
        // The config and transport are not stored: the key stays out of reach of the client's fields.
        $transport = new Psr18Transport(Config::resolve($apiKey, $options));
        $this->payments = new HttpPaymentRepository($transport);
        $this->payouts = new HttpPayoutRepository($transport);
        $this->disputes = new HttpDisputeRepository($transport);
        $this->testFunds = new HttpTestFundsRepository($transport);
    }
}
