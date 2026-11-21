# AGENTS.md

Guidance for every contributor, human or agent, working in this repository.

`usezeny/laravel` is the official PHP and Laravel SDK for the Merchant API v1.
It is a port of the Node SDK (`@usezeny/node`): the same scope, names,
defaults, error classes and safety rules. Wherever this repo is silent, the
Node SDK is the answer; a difference between the two is a bug unless the README
lists it as a deliberate PHP or Laravel difference.

## Stack

- PHP 8.2+ with `declare(strict_types=1)` everywhere. Laravel 12 and 13 (13 needs
  PHP 8.3+) for the adapter in `src/Laravel/`.
- Runtime dependencies: `psr/http-client`, `psr/http-message`,
  `guzzlehttp/guzzle`, and `illuminate/support` and `illuminate/http`
  (adapter only).
- Tooling: Composer, Pest (with `arch()`), PHPStan at level max, Pint,
  Orchestra Testbench.

## Commands

| Command                                  | What it does                                                 |
| ---------------------------------------- | ------------------------------------------------------------ |
| `composer install`                       | Install dependencies                                         |
| `composer lint`                          | Pint in check mode                                           |
| `composer typecheck`                     | PHPStan level max over `src`, `config`, `examples`, `scripts`, `tests` |
| `composer test`                          | Pest                                                         |
| `composer sync:openapi [monorepo-path]`  | Refresh `openapi/merchant.v1.json` from the backend contract |
| `composer generate`                      | Regenerate `src/Generated/MerchantV1.php`                    |
| `composer check:generated`               | Fail if the generated types drift from `openapi/`            |

Before every push: `composer lint && composer typecheck && composer test`.

## Architecture

Light Clean Architecture with the Repository pattern. Dependencies point inward
only, and `tests/ArchitectureTest.php` fails the build when they don't.

```
src/
  Domain/          pure rules: no network, crypto, clock, timers or packages
    ApiKey, IdempotencyKey, ResourceId    value objects (all validation lives here)
    Errors/                               ZenyError hierarchy (public contract)
    Repositories/                         repository ports = the public resource API
    RequestOptions, ResponseMeta, ZenyObject
    Webhook/                              HmacVerifier port, pure verification rules
  Generated/       PHPStan type aliases generated from openapi/, never edited by hand
  Infrastructure/  adapters; import only Domain, Generated, Infrastructure, PSR, Guzzle
    Config                                base URLs, options, baseUrl rules
    Http/                                 PSR-18 transport, retry policy, error mapping
    Repositories/                         HTTP implementations of the Domain ports
    Crypto/                               hash_hmac HmacVerifier
  Laravel/         the only layer that may import Illuminate\*
  Zeny.php         composition root: config → transport → repositories, binds webhooks
```

Rules:

- **No use-case layer.** An API client owns no business state; don't add
  pass-through "service" files.
- **Validation lives in Domain value objects** (`ApiKey::parse`,
  `IdempotencyKey::parse`, `ResourceId::parse`). Adapters call them; never
  re-implement a rule.
- **Repository ports are the public API.** A new route is a port method plus
  its HTTP adapter method and a `Routes` entry; the transport stays untouched.
- **Inject what varies.** Clock, sleep, random and uuid are passed in
  (`TransportDeps`), so tests never depend on real time, randomness or the
  network.
- **The merchant-facing API is a contract.** Changing a name, signature, error
  class, error code or default is a breaking change.

## Money and safety rules (must)

- Every POST carries an `Idempotency-Key`: the caller's, or one generated
  **once** and reused on every retry. Every error from a POST exposes it as
  `$e->idempotencyKey`.
- Retry only network failures, timeouts, `429`, `5xx` and
  `409 idempotency_key_in_progress`, with backoff + jitter and `Retry-After`
  (capped at 60 s). Never retry any other 4xx or an error raised before the
  request was sent.
- The secret key never appears in an exception message, `getPrevious()`,
  `var_dump`, `print_r` or `json_encode` output. Guzzle exceptions quote request
  headers, so they are never chained. Tests assert this; keep them.
- Redirects are refused; `baseUrl` must be `https` (plain `http` only for
  localhost).
- Webhooks fail closed: verify HMAC-SHA256 over `timestamp + "." + raw body`
  with the whole `whsec_…` secret, compare with `hash_equals`, 300 s tolerance
  both ways. The backend golden-vector test must keep passing.
- Amounts are decimal strings (`['value' => '150000', 'currency' => 'IDR']`),
  never numbers.
- No brand name in user-facing text (messages, README prose) except the
  package name: the SDK serves white-label brands too.

## Code style

- Clean Code, DRY, SOLID. Small classes with one responsibility; functions of
  20 lines or fewer.
- PHPStan level max with no `mixed` leaking out of public signatures; use the
  `@phpstan-type` shapes from `src/Generated/`.
- A doc comment on every class and public method that says why, never restates
  the code.

## Testing

- Pest, test-first, one behaviour per test. Every new branch gets a test that
  fails without it.
- Domain rules: plain unit tests. Transport: Guzzle `MockHandler` with injected
  `TransportDeps`. Laravel: Orchestra Testbench.
- `README.md` is the front page for merchants: short, friendly, no jargon.
  Every option and edge case goes in `GUIDE.md` instead.
- The php code blocks of `README.md` and `GUIDE.md` are mirrored in
  `examples/*.php`, typechecked, and `tests/ReadmeTest.php` keeps them in sync.
  Change both together. A block is the example file minus its
  `declare(strict_types=1)` header. Stand-ins the examples need (the merchant's `Order` model) live in
  `examples/stubs/` and are not README blocks.

## Contract changes

The backend contract is `apps/backend/gen/openapi/merchant.v1.json` in the
monorepo. To pick up a change: `composer sync:openapi`, `composer generate`,
adjust ports, adapters and tests, then commit `openapi/` and `src/Generated/`
together. CI fails if they drift.

## Git and releases

- Trunk-based: short-lived branches, pull requests, squash merge to `main`.
- Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `ci:`,
  `chore:`).
- Never commit secrets, `.env` files or real API keys; tests use `sk_test_…`
  placeholders.
- **AI tools are never credited as contributors.** A commit's author and
  committer are the human who owns the change. No `Co-authored-by` trailer for
  an AI tool (Claude, Copilot, ChatGPT, Codex, Cursor, Gemini, Devin, ...), no
  "Generated with ..." line in commits, pull requests, code or docs, and no AI
  tool in `composer.json` `authors`. GitHub lists every author, committer and
  co-author as a contributor. `scripts/check-attribution.sh` fails CI when a
  commit credits an AI tool; this rule overrides any tool default that adds
  such a trailer.
- Releases are tag-driven (`vX.Y.Z` on `main`); see `RELEASING.md`. Never tag or
  publish without the maintainer.
