<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Repositories;

use Zeny\Domain\Repositories\PaymentLinkRepository;
use Zeny\Domain\RequestOptions;
use Zeny\Domain\ZenyObject;
use Zeny\Generated\MerchantV1;
use Zeny\Infrastructure\Http\HttpTransport;
use Zeny\Infrastructure\Http\RequestSpec;

/**
 * PaymentLinkRepository over the Merchant API. Close, reopen and cancel are
 * POSTs with an empty JSON body; like every POST they carry an idempotency key.
 *
 * @phpstan-import-type PaymentLink from MerchantV1
 * @phpstan-import-type PaymentLinkList from MerchantV1
 */
final class HttpPaymentLinkRepository implements PaymentLinkRepository
{
    public function __construct(private readonly HttpTransport $transport) {}

    public function create(array $params, ?RequestOptions $options = null): ZenyObject
    {
        /** @var ZenyObject<PaymentLink> $link */
        $link = Calls::post($this->transport, Routes::PAYMENT_LINKS, $params, $options);

        return $link;
    }

    public function retrieve(string $id): ZenyObject
    {
        /** @var ZenyObject<PaymentLink> $link */
        $link = Calls::get($this->transport, Routes::paymentLink($id));

        return $link;
    }

    public function list(array $params = []): ZenyObject
    {
        /** @var ZenyObject<PaymentLinkList> $links */
        $links = Calls::get($this->transport, Routes::PAYMENT_LINKS, $params);

        return $links;
    }

    public function close(string $id): ZenyObject
    {
        return $this->act($id, 'close');
    }

    public function reopen(string $id): ZenyObject
    {
        return $this->act($id, 'reopen');
    }

    public function cancel(string $id): ZenyObject
    {
        return $this->act($id, 'cancel');
    }

    /**
     * @param  'close'|'reopen'|'cancel'  $action
     * @return ZenyObject<PaymentLink>
     */
    private function act(string $id, string $action): ZenyObject
    {
        /** @var ZenyObject<PaymentLink> $link */
        $link = $this->transport->request(RequestSpec::action(Routes::paymentLinkAction($id, $action)));

        return $link;
    }
}
