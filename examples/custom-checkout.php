<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Zeny\Laravel\Facades\Zeny;

Route::post('/orders/{order}/checkout', function (Order $order): RedirectResponse|string {
    $payment = Zeny::payments()->create([
        'reference' => "order-{$order->id}", // your order id, unique in your project
        'amount' => ['value' => $order->total, 'currency' => 'IDR'],
    ])->toArray();

    $attempt = Zeny::payments()->createAttempt($payment['id'], ['instrument' => 'BCA'])->toArray();

    $next = $attempt['nextAction'];

    return match ($next['type'] ?? null) {
        'display_va' => 'Transfer to '.($next['displayVa']['bank'] ?? '').' '.($next['displayVa']['number'] ?? ''),
        'display_qr' => $next['displayQr']['data'] ?? '', // render this as a QR code
        'redirect' => redirect()->away($next['redirect']['url'] ?? ''),
        // No action, or a type this version does not know: wait for the webhook.
        default => "Attempt is {$attempt['status']}; wait for payment.paid",
    };
});
