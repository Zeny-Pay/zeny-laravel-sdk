<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Zeny\Laravel\Middleware\VerifyZenyWebhook;

Route::post('/webhooks/payments', function (Request $request): Response {
    $event = VerifyZenyWebhook::event($request); // checked by the zeny.webhook middleware

    DB::transaction(function () use ($event): void {
        // A unique key on id: a repeated event inserts nothing and changes nothing.
        $isNew = DB::table('webhook_events')->insertOrIgnore(['id' => $event['id'], 'type' => $event['type']]) === 1;
        if (! $isNew || $event['type'] !== 'payment.paid') {
            return;
        }
        $payment = $event['data']['object'];
        // A link payment carries its pl_ id; an API payment carries your reference.
        if ($payment['paymentLink'] !== '') {
            Order::markPaidByLink($payment['paymentLink']);
        } else {
            Order::markPaid($payment['reference']);
        }
    });

    // 204 only after the commit. An exception answers 500, so the event comes again.
    return response()->noContent();
})->middleware('zeny.webhook')->withoutMiddleware(ValidateCsrfToken::class);
