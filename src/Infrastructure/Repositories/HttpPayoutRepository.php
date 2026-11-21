<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\Repositories\PayoutRepository;
use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Http\HttpTransport;

/**
 * PayoutRepository over the Merchant API.
 *
 * @phpstan-import-type Payout from MerchantV1
 */
final class HttpPayoutRepository implements PayoutRepository
{
    public function __construct(private readonly HttpTransport $transport) {}

    public function create(array $params, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<Payout> $payout */
        $payout = Calls::post($this->transport, Routes::PAYOUTS, $params, $options);

        return $payout;
    }

    public function retrieve(string $id): ZenyObject
    {
        /** @var ZenyObject<Payout> $payout */
        $payout = Calls::get($this->transport, Routes::payout($id));

        return $payout;
    }

    /** Sends an empty JSON body, as the API expects. */
    public function cancel(string $id, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<Payout> $payout */
        $payout = Calls::post($this->transport, Routes::payoutCancel($id), [], $options);

        return $payout;
    }
}
