<?php

declare(strict_types=1);

/*
 * Merchant API settings. Publish with:
 *     php artisan vendor:publish --tag=zeny-config
 */
return [
    // Secret key from the dashboard: sk_test_... for Sandbox, sk_live_... for Production.
    'secret_key' => env('ZENY_SECRET_KEY'),

    // Webhook signing secret (whsec_...), used by the zeny.webhook middleware.
    'webhook_secret' => env('ZENY_WEBHOOK_SECRET'),

    // Leave null to pick the API host from the key prefix.
    'base_url' => env('ZENY_BASE_URL'),

    'max_retries' => env('ZENY_MAX_RETRIES', 2),

    // Leave null for the 30000 ms default. A bound zeny.http_client owns its
    // own timeout, so setting this as well is refused.
    'timeout_ms' => env('ZENY_TIMEOUT_MS'),
];
