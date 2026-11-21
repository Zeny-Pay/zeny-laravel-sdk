<?php

declare(strict_types=1);

use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\AuthenticationError;
use Zeny\Domain\Errors\ConflictError;
use Zeny\Domain\Errors\ConnectionError;
use Zeny\Domain\Errors\IdempotencyError;
use Zeny\Domain\Errors\InvalidRequestError;
use Zeny\Domain\Errors\NotFoundError;
use Zeny\Domain\Errors\OverloadedError;
use Zeny\Domain\Errors\PermissionError;
use Zeny\Domain\Errors\ProviderUnavailableError;
use Zeny\Domain\Errors\RateLimitError;
use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Domain\Errors\ZenyError;

function invalidRequest(): ZenyError
{
    return new InvalidRequestError(
        type: 'invalid_request',
        code: 'amount_too_small',
        message: 'amount is too small',
        param: 'amount.value',
        details: [['param' => 'amount.value', 'code' => 'min', 'message' => 'too small']],
        requestId: 'req_1',
        statusCode: 400,
        idempotencyKey: 'idem-1',
    );
}

test('code is the string error code', function () {
    expect(invalidRequest()->code)->toBe('amount_too_small');
});

test('getCode returns the same string', function () {
    expect(invalidRequest()->getCode())->toBe('amount_too_small');
});

test('the message is the API message', function () {
    expect(invalidRequest()->getMessage())->toBe('amount is too small');
});

test('keeps every field', function () {
    $error = invalidRequest();

    expect([$error->type, $error->param, $error->requestId, $error->statusCode, $error->idempotencyKey])
        ->toBe(['invalid_request', 'amount.value', 'req_1', 400, 'idem-1']);
});

test('keeps the details', function () {
    expect(invalidRequest()->details)->toBe([['param' => 'amount.value', 'code' => 'min', 'message' => 'too small']]);
});

test('optional fields default to null and no details', function () {
    $error = new ZenyError('api_error', 'x', 'm');

    expect([$error->param, $error->details, $error->requestId, $error->statusCode, $error->idempotencyKey])
        ->toBe([null, [], null, null, null]);
});

$subclasses = [
    InvalidRequestError::class, AuthenticationError::class, PermissionError::class, NotFoundError::class,
    ConflictError::class, IdempotencyError::class, ProviderUnavailableError::class, ApiError::class,
    SignatureVerificationError::class, RateLimitError::class, OverloadedError::class,
];
foreach ($subclasses as $class) {
    test("{$class} is a ZenyError", function () use ($class) {
        expect(new $class('t', 'c', 'm'))->toBeInstanceOf(ZenyError::class);
    });
}

test('RateLimitError keeps retryAfterSec', function () {
    expect((new RateLimitError('rate_limit', 'too_many', 'm', retryAfterSec: 3))->retryAfterSec)->toBe(3);
});

test('OverloadedError keeps retryAfterSec 0', function () {
    expect((new OverloadedError('overloaded', 'busy', 'm', retryAfterSec: 0))->retryAfterSec)->toBe(0);
});

test('retryAfterSec defaults to null', function () {
    expect((new RateLimitError('rate_limit', 'too_many', 'm'))->retryAfterSec)->toBeNull();
});

test('a connection error has an unknown outcome', function () {
    expect((new ConnectionError('timeout', 'm'))->outcomeUnknown)->toBeTrue();
});

test('a connection error has the connection_error type', function () {
    expect((new ConnectionError('network_error', 'm', 'idem-1'))->type)->toBe('connection_error');
});

test('a connection error keeps the idempotency key', function () {
    expect((new ConnectionError('network_error', 'm', 'idem-1'))->idempotencyKey)->toBe('idem-1');
});
