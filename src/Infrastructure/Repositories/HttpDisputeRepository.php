<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\Repositories\DisputeRepository;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Http\HttpTransport;

/**
 * DisputeRepository over the Merchant API.
 *
 * @phpstan-import-type Dispute from MerchantV1
 * @phpstan-import-type DisputeList from MerchantV1
 */
final class HttpDisputeRepository implements DisputeRepository
{
    public function __construct(private readonly HttpTransport $transport) {}

    public function list(array $params = []): ZenyObject
    {
        /** @var ZenyObject<DisputeList> $disputes */
        $disputes = Calls::get($this->transport, Routes::DISPUTES, $params);

        return $disputes;
    }

    public function retrieve(string $id): ZenyObject
    {
        /** @var ZenyObject<Dispute> $dispute */
        $dispute = Calls::get($this->transport, Routes::dispute($id));

        return $dispute;
    }
}
