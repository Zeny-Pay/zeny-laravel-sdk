<?php

declare(strict_types=1);

namespace Zeny\Domain\Repositories;

use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;

/**
 * Create and manage payment links: hosted checkout pages you share by URL.
 * Each payer who pays through one makes a payment whose `paymentLink` is the
 * link's id.
 *
 * @phpstan-import-type PaymentLink from MerchantV1
 * @phpstan-import-type PaymentLinkList from MerchantV1
 * @phpstan-import-type CreatePaymentLinkParams from MerchantV1
 * @phpstan-import-type ListPaymentLinksParams from MerchantV1
 */
interface PaymentLinkRepository
{
    /**
     * Creates a link. Pass an idempotency key derived from your order so a
     * retry returns the same link instead of a second one. Set `maxPayments`
     * to 1 for a link per order: once paid, a new payment on it fails with
     * `ConflictError` code `payment_link_closed`.
     *
     * @param  CreatePaymentLinkParams  $params
     * @return ZenyObject<PaymentLink>
     */
    public function create(array $params, ?RequestOptions $options = null): ZenyObject;

    /**
     * Retrieves a link by id (`pl_…`).
     *
     * @return ZenyObject<PaymentLink>
     */
    public function retrieve(string $id): ZenyObject;

    /**
     * Lists links, newest first, with optional status, date, limit and cursor filters.
     *
     * @param  ListPaymentLinksParams  $params
     * @return ZenyObject<PaymentLinkList>
     */
    public function list(array $params = []): ZenyObject;

    /**
     * close, reopen and cancel are idempotent by state, so the API reads no
     * body and no Idempotency-Key for them and none is sent; a retry is safe.
     *
     * Stops an active link taking payments; reopen it to take them again.
     *
     * @return ZenyObject<PaymentLink>
     */
    public function close(string $id): ZenyObject;

    /**
     * Lets a closed link take payments again. An expired link cannot reopen.
     *
     * @return ZenyObject<PaymentLink>
     */
    public function reopen(string $id): ZenyObject;

    /**
     * Cancels a link that has taken no payment. Final.
     *
     * @return ZenyObject<PaymentLink>
     */
    public function cancel(string $id): ZenyObject;
}
