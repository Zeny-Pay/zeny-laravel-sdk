# usezeny/laravel

Accept Indonesian payments from Laravel: the official PHP SDK for the Merchant API v1, with a Laravel service provider, facade and webhook middleware.

## Install

```bash
composer require usezeny/laravel
```

Requires PHP 8.2+ and Laravel 12, or PHP 8.3+ and Laravel 13. The service provider and the `Zeny` facade register themselves. The client works outside Laravel too (see [Environments and `baseUrl`](#environments-and-baseurl)).

Add your keys to `.env`. Get a Sandbox secret key (`sk_test_...`) from the dashboard Developers page under `/test`; Sandbox money is never real.

```dotenv
ZENY_SECRET_KEY=sk_test_...
ZENY_WEBHOOK_SECRET=whsec_...
```

## Quickstart: a payment link

A payment link is a hosted checkout page. Create one for the order, keep its id, and send the payer to its `url`. The page offers every instrument the payer can use, or only those you list in `instruments` (`["BCA", "QRIS"]`), and returns the payer to `successRedirectUrl`.

```php
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
```

Amounts are decimal strings, never numbers. Pass your own idempotency key derived from the order, so a retried request returns the same link instead of a second one. Do not mark the order paid when the payer comes back: wait for the `payment.paid` webhook, which carries the link id in `paymentLink`.

`maxPayments` caps how many paid payments a link takes (0 or omitted: no limit); once reached, a new payment on the link fails with `ConflictError` code `payment_link_closed`, and payers already paying may still finish, so refund any extra. `requiredPayerFields` lists the details a payer must give (`name`, `email`, `phone`); a link you read back may hold a value added later, so accept unknown ones. `description` (one line, up to 500 characters) is shown under the name, and `expiredRedirectUrl` (an `https` URL) is where a payer who opens an expired link is sent.

The other calls on a link: `retrieve($id)`, `list(['status' => 'active', 'limit' => 20])` (a cursor list: pass `nextCursor` back as `cursor` while `hasMore` is true; canceled links are left out), `close($id)` (stops new payments), `reopen($id)` (fails for an expired or canceled link) and `cancel($id)` (only while the link has no payments; otherwise `ConflictError` with code `payment_link_has_payments`).

## Custom checkout

To build the checkout page yourself, create a payment, start an attempt with the payer's chosen instrument, then show the payer what `nextAction` asks.

```php
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
```

`instrument` is a bank code for a virtual account (`"BCA"`), `"QRIS"` or an e-wallet (`"GOPAY"`). A refused attempt is not an exception: it comes back with `status: "failed"` and a `failure`; start a new attempt with a new key. Here too, mark the order paid only on the `payment.paid` webhook.

### Results

Every call returns a read-only `ZenyObject`. Read fields with array access (`$payment['id']`), or call `toArray()` for the whole body with its PHPStan array shape, so static analysis knows every field. `json_encode($result)` gives the API body unchanged. `$result->lastResponse` holds `statusCode`, `requestId`, `idempotentReplayed`, `idempotencyKey` and `headers`, and is left out of `toArray()` and `json_encode`.

## Laravel setup

The package reads its settings from `config/zeny.php`. Publish the file only if you want to change it:

```bash
php artisan vendor:publish --tag=zeny-config
```

| Variable | Default | Meaning |
|---|---|---|
| `ZENY_SECRET_KEY` | none | Secret key; the prefix picks Sandbox (`sk_test_`) or Production (`sk_live_`). |
| `ZENY_WEBHOOK_SECRET` | none | Webhook signing secret (`whsec_...`) for the `zeny.webhook` middleware. |
| `ZENY_BASE_URL` | from the key | API host, for white-label brands only. |
| `ZENY_MAX_RETRIES` | `2` | Automatic retries of transient failures. |
| `ZENY_TIMEOUT_MS` | `30000` | Timeout of each request, in milliseconds. Leave it unset when you bind your own HTTP client. |

The client is a lazy singleton: a missing or malformed key throws when it is first used, not at boot. Inject `Zeny\Zeny` or use the facade (`Zeny::payments()`, `Zeny::paymentLinks()`, ...). To send requests through your own HTTP client, bind a PSR-18 client as `zeny.http_client` in the container; that client then owns its timeout and redirect policy, and setting `ZENY_TIMEOUT_MS` as well throws.

## Webhooks

Add an https endpoint in the dashboard, pick its events and copy its secret (`whsec_...`). The `zeny.webhook` middleware checks the signature and the timestamp of the raw body, answers 400 when they fail, and hands the route a typed event through `VerifyZenyWebhook::event($request)` (also stored in the `zenyEvent` request attribute). Exclude the route from CSRF protection.

Insert each event under a unique constraint on its `id`: events can repeat, and a repeat must change nothing. Answer 2xx only after the insert commits, and 500 if it fails, so the event comes again. Events can arrive out of order: ignore one whose `resourceVersion` is older than the one you hold. Skip types you do not know. Sandbox events arrive with `mode: "test"`.

Event types: `payment.paid`, `payment.expired`, `payment_attempt.failed`, `payout.paid`, `payout.failed`, `payout.rejected`, `payout.canceled`, `dispute.opened`, `dispute.closed`.

```php
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
```

This route records the event and updates the order in one transaction, so a failure rolls both back and answers 500. For slower work, store the event the same way and process stored events from a queued job.

Without Laravel, pass the raw body and the headers to `constructEvent` yourself:

```php
use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Zeny;

$secret = (string) getenv('ZENY_WEBHOOK_SECRET'); // whsec_...
$pdo = new PDO((string) getenv('DATABASE_DSN'), options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$rawBody = (string) file_get_contents('php://input'); // the raw body, byte for byte
try {
    $event = Zeny::webhooks()->constructEvent($rawBody, getallheaders(), $secret);
} catch (SignatureVerificationError) {
    http_response_code(400);
    exit;
}

// A unique key on id: a repeat inserts nothing and still answers 200. A failed
// insert throws, PHP answers 500 and the event comes again.
$pdo->prepare('INSERT INTO webhook_events (id, type, payload) VALUES (?, ?, ?) ON CONFLICT (id) DO NOTHING')
    ->execute([$event['id'], $event['type'], $rawBody]);

// Answered only after the insert committed. A worker processes stored events by
// type and skips types it does not know; failures there never lose the event.
http_response_code(200);
```

## Errors

Every failure is a `Zeny\Domain\Errors\ZenyError` subclass (`InvalidRequestError`, `AuthenticationError`, `PermissionError`, `NotFoundError`, `ConflictError`, `IdempotencyError`, `RateLimitError`, `OverloadedError`, `ProviderUnavailableError`, `ApiError`, `ConnectionError`, `SignatureVerificationError`). Branch on `$e->code`, which is stable, keep a default branch because new codes appear, and log `$e->requestId` so support can find the request. Invalid arguments (a malformed key, a bad option) throw `InvalidArgumentException` before any request.

```php
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
```

## Idempotency and retries

Every POST sends an `Idempotency-Key`. The SDK generates one for you and reuses it on each automatic retry, so a retried request never runs twice. It retries transient failures (429, 5xx, timeouts, dropped connections) up to `maxRetries` times, 2 by default, with backoff, and honours `Retry-After`.

Pass your own key with `RequestOptions` to make a retry safe across restarts, for example from your order id:

```php
use Zeny\Domain\RequestOptions;
use Zeny\Laravel\Facades\Zeny;

// Your own key: derive it from the order so a restarted worker repeats the same intent.
$payment = Zeny::payments()->create(
    ['reference' => 'order-123', 'amount' => ['value' => '150000', 'currency' => 'IDR']],
    new RequestOptions(idempotencyKey: 'order-123:create'),
);

echo $payment->toArray()['id'];
```

When a call still fails and you retry it yourself, reuse `$e->idempotencyKey`: a fresh key could charge twice. A `ConnectionError` means the outcome is unknown, so this is the case that matters.

```php
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
```

## Environments and `baseUrl`

The key prefix picks the environment: `sk_test_` talks to Sandbox, `sk_live_` to Production. A Sandbox key is refused in Production and the other way round. Keep keys in configuration, not code. A white-label brand has its own host: set `ZENY_BASE_URL`, or `baseUrl` when you build the client yourself. `timeoutMs` (default 30000), `maxRetries` (default 2) and `httpClient` (any PSR-18 client; the default is Guzzle with redirects off) are the other options. A custom `httpClient` owns its timeout, so it cannot be combined with `timeoutMs`.

```php
use Zeny\Zeny;

// A white-label brand has its own host; the key prefix still picks Sandbox or Production.
$brandClient = new Zeny((string) getenv('ZENY_SECRET_KEY'), [
    'baseUrl' => 'https://api.brand.example',
    'timeoutMs' => 15000,
    'maxRetries' => 3,
]);
```

## Differences from the Node SDK

- Results are `ZenyObject`s with `->lastResponse`, because PHP has no hidden properties; `json_encode` still gives the body only.
- Types are PHPStan array shapes generated from the OpenAPI contract, reached through `toArray()`.
- The HTTP client is any PSR-18 client instead of `fetch`. A client you pass in owns its own timeout and redirect policy.
- `constructEvent` is synchronous.

## License

MIT
