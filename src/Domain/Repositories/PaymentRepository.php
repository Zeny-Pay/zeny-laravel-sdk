<?php

declare(strict_types=1);

namespace Zeny\Domain\Repositories;

use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;

/**
 * Create and read payments and their attempts.
 *
 * @phpstan-import-type Payment from MerchantV1
 * @phpstan-import-type PaymentAttempt from MerchantV1
 * @phpstan-import-type CreatePaymentParams from MerchantV1
 * @phpstan-import-type CreatePaymentAttemptParams from MerchantV1
 */
interface PaymentRepository
{
    /**
     * Creates a payment.
     *
     * @param  CreatePaymentParams  $params
     * @return ZenyObject<Payment>
     */
    public function create(array $params, ?RequestOptions $options = null): ZenyObject;

    /**
     * Retrieves a payment by id.
     *
     * @return ZenyObject<Payment>
     */
    public function retrieve(string $id): ZenyObject;

    /**
     * Starts a payment attempt with a chosen instrument.
     *
     * @param  CreatePaymentAttemptParams  $params
     * @return ZenyObject<PaymentAttempt>
     */
    public function createAttempt(string $paymentId, array $params, ?RequestOptions $options = null): ZenyObject;
}
