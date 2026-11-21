<?php

declare(strict_types=1);

namespace Zeny\Domain\Repositories;

use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;

/**
 * Credit test funds. Exists in Sandbox only.
 *
 * @phpstan-import-type TestFunds from MerchantV1
 * @phpstan-import-type CreateTestFundsParams from MerchantV1
 */
interface TestFundsRepository
{
    /**
     * Credits test funds to the merchant balance.
     *
     * @param  CreateTestFundsParams  $params
     * @return ZenyObject<TestFunds>
     */
    public function create(array $params, ?RequestOptions $options = null): ZenyObject;
}
