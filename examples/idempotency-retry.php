<?php

declare(strict_types=1);

use Zeny\Domain\Errors\ConnectionError;
use Zeny\Domain\RequestOptions;
use Zeny\Laravel\Facades\Zeny;

$params = ['reference' => 'order-124', 'amount' => ['value' => '150000', 'currency' => 'IDR']];

try {
    Zeny::payments()->create($params);
} catch (ConnectionError $e) {
    // Without the original key a retry would get a fresh one, so give up instead.
    if ($e->idempotencyKey === null) {
        throw $e;
    }
    // The outcome is unknown. Reusing the failed call's key cannot double-charge:
    // if the first request went through, you get that payment back.
    Zeny::payments()->create($params, new RequestOptions(idempotencyKey: $e->idempotencyKey));
}
