<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\ConnectionError;
use Zeny\Domain\Errors\InvalidRequestError;
use Zeny\Infrastructure\Http\RequestSpec;
use Zeny\Infrastructure\Http\Version;
use Zeny\Tests\Support\TransportHarness;

/** @return array{error: array{type: string, code: string, message: string}} */
function apiErrorBody(string $type, string $code): array
{
    return ['error' => ['type' => $type, 'code' => $code, 'message' => 'm']];
}

function timeoutException(): ConnectException
{
    return new ConnectException(
        'cURL error 28: Operation timed out (Authorization: Bearer '.TransportHarness::KEY.')',
        new Request('POST', 'https://x'),
        null,
        ['errno' => 28],
    );
}

test('GET builds the URL with the API prefix and drops null query values', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, ['id' => 'a'])]);
    $t->transport->request(RequestSpec::get('/payments', ['limit' => 5, 'x' => null, 's' => 'a b']));

    expect((string) $t->request(0)->getUri())->toBe('https://snorlax.usezeny.com/api/merchant/v1/payments?limit=5&s=a+b');
});

test('an empty query adds no question mark', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x', ['a' => null]));

    expect((string) $t->request(0)->getUri())->toBe('https://snorlax.usezeny.com/api/merchant/v1/x');
});

test('GET sends exactly the auth, accept and user-agent headers', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x'));
    $headers = array_map(fn (array $values): string => implode(',', $values), $t->request(0)->getHeaders());
    unset($headers['Host']);

    expect($headers)->toBe([
        'Authorization' => 'Bearer '.TransportHarness::KEY,
        'Accept' => 'application/json',
        'User-Agent' => 'zeny-laravel-sdk/'.Version::VALUE,
    ]);
});

test('GET sends no body', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x'));

    expect((string) $t->request(0)->getBody())->toBe('');
});

test('POST sends the JSON body', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, ['id' => 'p'])]);
    $t->transport->request(RequestSpec::post('/payments', ['amount' => ['value' => '1.00', 'currency' => 'IDR'], 'url' => 'https://a/b']));

    expect((string) $t->request(0)->getBody())->toBe('{"amount":{"value":"1.00","currency":"IDR"},"url":"https://a/b"}');
});

test('POST adds the content type and a generated idempotency key', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, [])]);
    $t->transport->request(RequestSpec::post('/payments', ['a' => 1]));

    expect([$t->request(0)->getHeaderLine('Content-Type'), $t->request(0)->getHeaderLine('Idempotency-Key')])
        ->toBe(['application/json', 'uuid-1']);
});

test('POST with no body sends {}', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, [])]);
    $t->transport->request(RequestSpec::post('/x'));

    expect((string) $t->request(0)->getBody())->toBe('{}');
});

test('POST with an empty array body sends {}, never []', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, [])]);
    $t->transport->request(RequestSpec::post('/x', []));

    expect((string) $t->request(0)->getBody())->toBe('{}');
});

test('the caller key is forwarded as given', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, [])]);
    $t->transport->request(RequestSpec::post('/x', null, 'mine-1'));

    expect($t->request(0)->getHeaderLine('Idempotency-Key'))->toBe('mine-1');
});

test('an invalid caller key throws before any request', function (): void {
    $t = new TransportHarness([]);

    expect(fn () => $t->transport->request(RequestSpec::post('/x', null, "bad\nkey")))->toThrow(InvalidArgumentException::class);
});

test('an invalid caller key sends nothing', function (): void {
    $t = new TransportHarness([]);
    try {
        $t->transport->request(RequestSpec::post('/x', null, ''));
    } catch (InvalidArgumentException) {
    }

    expect(count($t->requests))->toBe(0);
});

test('a retried POST replays the same key', function (): void {
    $t = new TransportHarness([TransportHarness::json(503, apiErrorBody('provider_unavailable', 'down')), TransportHarness::json(201, ['id' => 'p'])]);
    $t->transport->request(RequestSpec::post('/payments'));

    expect([$t->request(0)->getHeaderLine('Idempotency-Key'), $t->request(1)->getHeaderLine('Idempotency-Key')])->toBe(['uuid-1', 'uuid-1']);
});

test('a retried request returns the later success with its meta', function (): void {
    $t = new TransportHarness([TransportHarness::json(503, apiErrorBody('provider_unavailable', 'down')), TransportHarness::json(201, ['id' => 'p'], ['X-Request-ID' => 'req_2'])]);
    $object = $t->transport->request(RequestSpec::post('/payments'));

    expect([$object['id'], $object->lastResponse->statusCode, $object->lastResponse->requestId, $object->lastResponse->idempotencyKey])
        ->toBe(['p', 201, 'req_2', 'uuid-1']);
});

test('it backs off with full jitter between retries', function (): void {
    $t = new TransportHarness([TransportHarness::json(500, []), TransportHarness::json(500, []), TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x'));

    expect($t->sleeps)->toBe([500, 1000]);
});

test('it stops after maxRetries and throws the last error', function (): void {
    $t = new TransportHarness(array_fill(0, 4, TransportHarness::json(500, apiErrorBody('api_error', 'boom'))), 2);
    $error = $t->caught(RequestSpec::get('/x'));

    expect([count($t->requests), $error->code])->toBe([3, 'boom']);
});

test('maxRetries 0 sends once', function (): void {
    $t = new TransportHarness([TransportHarness::json(500, []), TransportHarness::json(200, [])], 0);
    $t->caught(RequestSpec::get('/x'));

    expect(count($t->requests))->toBe(1);
});

test('a 4xx is not retried', function (): void {
    $t = new TransportHarness([TransportHarness::json(400, apiErrorBody('invalid_request', 'bad')), TransportHarness::json(200, [])]);
    $error = $t->caught(RequestSpec::post('/x'));

    expect([get_class($error), count($t->requests)])->toBe([InvalidRequestError::class, 1]);
});

test('a 409 idempotency_key_in_progress is retried', function (): void {
    $t = new TransportHarness([TransportHarness::json(409, apiErrorBody('idempotency_error', 'idempotency_key_in_progress')), TransportHarness::json(201, [])]);
    $t->transport->request(RequestSpec::post('/x'));

    expect(count($t->requests))->toBe(2);
});

test('it sleeps for Retry-After', function (): void {
    $t = new TransportHarness([TransportHarness::json(429, apiErrorBody('rate_limit', 'slow'), ['Retry-After' => '3']), TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x'));

    expect($t->sleeps)->toBe([3000]);
});

test('a 2xx that is not a JSON object gives unexpected_response', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, [1, 2])]);
    $error = $t->caught(RequestSpec::get('/x'));

    expect([get_class($error), $error->code, $error->getMessage()])
        ->toBe([ApiError::class, 'unexpected_response', 'The API returned a response that was not a JSON object.']);
});

test('a 2xx with an empty body gives unexpected_response', function (): void {
    $t = new TransportHarness([new Response(204)]);

    expect($t->caught(RequestSpec::get('/x'))->code)->toBe('unexpected_response');
});

test('a 2xx that is not a JSON object is not retried', function (): void {
    $t = new TransportHarness([new Response(200, [], 'ok'), TransportHarness::json(200, [])]);
    $t->caught(RequestSpec::get('/x'));

    expect(count($t->requests))->toBe(1);
});

test('an empty JSON object is a success', function (): void {
    $t = new TransportHarness([new Response(200, [], '{}')]);

    expect($t->transport->request(RequestSpec::get('/x'))->toArray())->toBe([]);
});

test('a timeout gives ConnectionError code timeout', function (): void {
    $t = new TransportHarness([timeoutException()], 0);
    $error = $t->caught(RequestSpec::post('/x'));

    expect([get_class($error), $error->code, $error->idempotencyKey])->toBe([ConnectionError::class, 'timeout', 'uuid-1']);
});

test('a timeout error never carries the key or the client exception', function (): void {
    $t = new TransportHarness([timeoutException()], 0);
    $error = $t->caught(RequestSpec::post('/x'));

    expect([str_contains($error->getMessage(), TransportHarness::KEY), $error->getPrevious()])->toBe([false, null]);
});

test('a connection failure gives ConnectionError code network_error', function (): void {
    $t = new TransportHarness([new ConnectException('Could not resolve host', new Request('GET', 'https://x'))], 0);

    expect($t->caught(RequestSpec::get('/x'))->code)->toBe('network_error');
});

test('a connection failure is retried', function (): void {
    $t = new TransportHarness([new ConnectException('reset', new Request('GET', 'https://x')), TransportHarness::json(200, [])]);
    $t->transport->request(RequestSpec::get('/x'));

    expect(count($t->requests))->toBe(2);
});

test('a redirect is an error, not followed', function (): void {
    $t = new TransportHarness([new Response(302, ['Location' => 'https://evil.example/']), TransportHarness::json(200, [])]);
    $error = $t->caught(RequestSpec::get('/x'));

    expect([get_class($error), $error->code, $error->statusCode, count($t->requests)])->toBe([ApiError::class, 'unexpected_response', 302, 1]);
});

test('idempotentReplayed is read from the Idempotent-Replayed header', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, ['id' => 'p'], ['Idempotent-Replayed' => 'true'])]);

    expect($t->transport->request(RequestSpec::post('/x'))->lastResponse->idempotentReplayed)->toBeTrue();
});

test('idempotentReplayed is false without the header', function (): void {
    $t = new TransportHarness([TransportHarness::json(201, [])]);

    expect($t->transport->request(RequestSpec::post('/x'))->lastResponse->idempotentReplayed)->toBeFalse();
});

test('response header names are lower-cased in the meta', function (): void {
    $t = new TransportHarness([TransportHarness::json(200, [], ['X-Request-ID' => 'req_1'])]);

    expect($t->transport->request(RequestSpec::get('/x'))->lastResponse->headers['x-request-id'] ?? null)->toBe(['req_1']);
});

test('a GET error carries no idempotency key', function (): void {
    $t = new TransportHarness([TransportHarness::json(404, apiErrorBody('not_found', 'missing'))]);

    expect($t->caught(RequestSpec::get('/x'))->idempotencyKey)->toBeNull();
});

test('an API error carries the request id from the header', function (): void {
    $t = new TransportHarness([TransportHarness::json(404, apiErrorBody('not_found', 'missing'), ['X-Request-ID' => 'req_9'])]);

    expect($t->caught(RequestSpec::get('/x'))->requestId)->toBe('req_9');
});
