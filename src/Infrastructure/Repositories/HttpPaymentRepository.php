<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\Repositories\PaymentRepository;
use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Http\HttpTransport;

/**
 * PaymentRepository over the Merchant API. The transport returns an untyped
 * body; the `@var` narrows it to the contract shape the route answers with.
 *
 * @phpstan-import-type Payment from MerchantV1
 * @phpstan-import-type PaymentAttempt from MerchantV1
 */
final class HttpPaymentRepository implements PaymentRepository
{
    public function __construct(private readonly HttpTransport $transport) {}

    public function create(array $params, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<Payment> $payment */
        $payment = Calls::post($this->transport, Routes::PAYMENTS, $params, $options);

        return $payment;
    }

    public function retrieve(string $id): ZenyObject
    {
        /** @var ZenyObject<Payment> $payment */
        $payment = Calls::get($this->transport, Routes::payment($id));

        return $payment;
    }

    public function createAttempt(string $paymentId, array $params, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<PaymentAttempt> $attempt */
        $attempt = Calls::post($this->transport, Routes::paymentAttempts($paymentId), $params, $options);

        return $attempt;
    }
}
