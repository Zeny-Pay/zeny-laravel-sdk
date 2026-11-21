<?php

declare(strict_types=1);

namespace Zeny\Domain\Repositories;

use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;

/**
 * Read disputes.
 *
 * @phpstan-import-type Dispute from MerchantV1
 * @phpstan-import-type DisputeList from MerchantV1
 * @phpstan-import-type ListDisputesParams from MerchantV1
 */
interface DisputeRepository
{
    /**
     * Lists disputes with optional status, limit and cursor filters.
     *
     * @param  ListDisputesParams  $params
     * @return ZenyObject<DisputeList>
     */
    public function list(array $params = []): ZenyObject;

    /**
     * Retrieves a dispute by id.
     *
     * @return ZenyObject<Dispute>
     */
    public function retrieve(string $id): ZenyObject;
}
