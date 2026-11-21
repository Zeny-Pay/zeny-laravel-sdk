<?php

declare(strict_types=1);

namespace Zeny\Domain\Repositories;

use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;

/**
 * Create, read and cancel payouts.
 *
 * @phpstan-import-type Payout from MerchantV1
 * @phpstan-import-type CreatePayoutParams from MerchantV1
 */
interface PayoutRepository
{
    /**
     * Creates a payout.
     *
     * @param  CreatePayoutParams  $params
     * @return ZenyObject<Payout>
     */
    public function create(array $params, ?RequestOptions $options = null): ZenyObject;

    /**
     * Retrieves a payout by id.
     *
     * @return ZenyObject<Payout>
     */
    public function retrieve(string $id): ZenyObject;

    /**
     * Cancels a payout.
     *
     * @return ZenyObject<Payout>
     */
    public function cancel(string $id, ?RequestOptions $options = null): ZenyObject;
}
