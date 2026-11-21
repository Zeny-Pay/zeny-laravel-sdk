<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\Repositories\TestFundsRepository;
use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Http\HttpTransport;

/**
 * TestFundsRepository over the Merchant API.
 *
 * @phpstan-import-type TestFunds from MerchantV1
 */
final class HttpTestFundsRepository implements TestFundsRepository
{
    public function __construct(private readonly HttpTransport $transport) {}

    public function create(array $params, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<TestFunds> $funds */
        $funds = Calls::post($this->transport, Routes::TEST_FUNDS, $params, $options);

        return $funds;
    }
}
