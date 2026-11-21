<?php

declare(strict_types=1);

use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\AuthenticationError;
use Zeny\Domain\Errors\ConflictError;
use Zeny\Domain\Errors\IdempotencyError;
use Zeny\Domain\Errors\InvalidRequestError;
use Zeny\Domain\Errors\NotFoundError;
use Zeny\Domain\Errors\OverloadedError;
use Zeny\Domain\Errors\PermissionError;
use Zeny\Domain\Errors\ProviderUnavailableError;
use Zeny\Domain\Errors\RateLimitError;
use Zeny\Domain\Errors\ZenyError;
use Zeny\Infrastructure\Http\MapError;

/**
 * @param  array<string, mixed>  $extra
 * @return array{error: array<string, mixed>}
 */
function errorBody(string $type, array $extra = []): array
{
    return ['error' => ['type' => $type, 'code' => 'c', 'message' => 'm'] + $extra];
}

$classes = [
    'invalid_request' => InvalidRequestError::class,
    'authentication' => AuthenticationError::class,
    'permission' => PermissionError::class,
    'not_found' => NotFoundError::class,
    'conflict' => ConflictError::class,
    'idempotency_error' => IdempotencyError::class,
    'rate_limit' => RateLimitError::class,
    'provider_unavailable' => ProviderUnavailableError::class,
    'overloaded' => OverloadedError::class,
    'api_error' => ApiError::class,
];
foreach ($classes as $type => $class) {
    test("type {$type} maps to its class", function () use ($type, $class): void {
        expect(MapError::map(400, errorBody($type), null))->toBeInstanceOf($class);
    });
}

test('an unknown type gives a plain ZenyError', function (): void {
    expect(get_class(MapError::map(400, errorBody('brand_new'), null)))->toBe(ZenyError::class);
});

test('an unknown type keeps its type string', function (): void {
    expect(MapError::map(400, errorBody('brand_new'), null)->type)->toBe('brand_new');
});

foreach (['null' => null, 'a string' => 'oops', 'a list' => [1, 2], 'no error key' => ['message' => 'x'], 'error not an object' => ['error' => 'x']] as $name => $body) {
    test("a body that is {$name} gives unexpected_response", function () use ($body): void {
        $error = MapError::map(502, $body, 'req_1');

        expect([get_class($error), $error->code, $error->getMessage()])
            ->toBe([ApiError::class, 'unexpected_response', 'Unexpected response from the API (HTTP 502)']);
    });
}

test('fields are copied from the body', function (): void {
    $error = MapError::map(422, errorBody('invalid_request', ['param' => 'amount', 'requestId' => 'req_body']), 'req_header', null, 'key-1');

    expect([$error->code, $error->getMessage(), $error->param, $error->requestId, $error->statusCode, $error->idempotencyKey])
        ->toBe(['c', 'm', 'amount', 'req_body', 422, 'key-1']);
});

test('missing fields get defaults', function (): void {
    $error = MapError::map(500, ['error' => ['type' => 42]], null);

    expect([$error->type, $error->code, $error->getMessage(), $error->param])->toBe(['api_error', 'unknown', 'API error (HTTP 500)', null]);
});

test('requestId falls back to the header', function (): void {
    expect(MapError::map(400, errorBody('conflict'), 'req_header')->requestId)->toBe('req_header');
});

test('details keep only well-formed entries', function (): void {
    $details = [
        ['param' => 'amount', 'code' => 'too_small', 'message' => 'too small', 'extra' => 1],
        ['param' => 'currency', 'code' => 7, 'message' => 'x'],
        'not an object',
        ['code' => 'missing_param', 'message' => 'x'],
    ];

    expect(MapError::map(400, errorBody('invalid_request', ['details' => $details]), null)->details)
        ->toBe([['param' => 'amount', 'code' => 'too_small', 'message' => 'too small']]);
});

test('details that are not a list become empty', function (): void {
    expect(MapError::map(400, errorBody('invalid_request', ['details' => ['a' => 1]]), null)->details)->toBe([]);
});

test('a rate limit carries Retry-After', function (): void {
    $error = MapError::map(429, errorBody('rate_limit'), null, 12);

    expect($error instanceof RateLimitError ? $error->retryAfterSec : 'wrong class')->toBe(12);
});

test('an overloaded error carries Retry-After', function (): void {
    $error = MapError::map(503, errorBody('overloaded'), null, 3);

    expect($error instanceof OverloadedError ? $error->retryAfterSec : 'wrong class')->toBe(3);
});
