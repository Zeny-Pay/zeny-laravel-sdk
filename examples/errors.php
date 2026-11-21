<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Zeny\Domain\Errors\ZenyError;
use Zeny\Laravel\Facades\Zeny;

try {
    Zeny::payments()->create([
        'reference' => 'order-1042',
        'amount' => ['value' => '150000', 'currency' => 'IDR'],
    ]);
} catch (ZenyError $e) {
    Log::error("Payment API error {$e->code}", ['requestId' => $e->requestId]); // quote requestId to support
    switch ($e->code) {
        case 'duplicate_reference':
            // The order already has a payment; read it instead of creating another.
            break;
        case 'amount_above_limit':
            // Not retryable: ask the payer for a smaller amount.
            break;
        case 'rate_limited':
            // The SDK already retried; back off before trying again.
            break;
        default:
            // New codes can appear. Log them and show a generic message.
            break;
    }
}
