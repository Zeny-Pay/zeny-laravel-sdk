# Changelog

## 0.1.0

- `Zeny` client for the Merchant API v1: payment links (create, retrieve, list, close, reopen, cancel), payments (create, retrieve, attempts), payouts (create, retrieve, cancel), disputes (list, retrieve) and Sandbox test funds.
- Results are read-only `ZenyObject`s with `lastResponse`; `toArray()` returns PHPStan array shapes generated from the API contract. Amounts are decimal strings.
- Automatic `Idempotency-Key`, reused across retries; `$e->idempotencyKey` for safe manual retries.
- Retries with backoff for transient failures, per-request timeout, `Retry-After` support.
- Typed error classes with a stable `code` and `requestId`.
- `constructEvent` webhook verification (HMAC-SHA256, timestamp tolerance) with typed events.
- Laravel 11 and 12: service provider with `config/zeny.php`, `Zeny` facade, and the `zeny.webhook` middleware with `VerifyZenyWebhook::event()`.
- Sandbox or Production chosen by the key prefix; `baseUrl` for white-label brands; any PSR-18 HTTP client.
