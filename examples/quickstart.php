<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Zeny\Domain\RequestOptions;
use Zeny\Laravel\Facades\Zeny;

Route::post('/orders/{order}/pay', function (Order $order): RedirectResponse {
    $link = Zeny::paymentLinks()->create([
        'name' => "Order #{$order->id}",
        'amount' => ['value' => $order->total, 'currency' => 'IDR'], // a decimal string, never a float
        'successRedirectUrl' => route('orders.thanks', $order),
        'maxPayments' => 1, // one order, one payment: the link closes once paid
        'requiredPayerFields' => ['email'], // the payer must give an email for the receipt
    ], new RequestOptions(idempotencyKey: "order-{$order->id}-link"))->toArray();

    $order->update(['payment_link' => $link['id']]); // pl_..., matched on the payment.paid webhook

    return redirect()->away($link['url']); // the hosted checkout page
});
