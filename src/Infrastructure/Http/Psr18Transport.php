<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\ConnectionError;
use Zeny\Domain\Errors\ZenyError;
use Zeny\Domain\IdempotencyKey;
use Zeny\Domain\ResponseMeta;
use Zeny\Domain\ZenyObject;
use Zeny\Infrastructure\Config;

/**
 * The PSR-18 transport: one pipeline for every route (auth, idempotency,
 * retries, typed errors). Nothing it throws carries the client's exception,
 * because those may quote request headers and so the bearer key.
 */
final class Psr18Transport implements HttpTransport
{
    private const API_PREFIX = '/api/merchant/v1';

    private const UNKNOWN_OUTCOME_MESSAGE = 'Could not reach the API; the outcome is unknown. '
        .'Retry with the same idempotency key ($e->idempotencyKey) or read the object.';

    private const CURL_TIMEOUT = 28;

    private readonly TransportDeps $deps;

    public function __construct(private readonly Config $config, ?TransportDeps $deps = null)
    {
        $this->deps = $deps ?? new TransportDeps;
    }

    public function request(RequestSpec $spec): ZenyObject
    {
        // Resolved once so every retry replays the same key; an invalid key throws before any I/O.
        $key = $spec->method === 'POST' ? $this->idempotencyKeyFor($spec) : null;
        for ($attempt = 0; ; $attempt++) {
            $result = $this->send($spec, $key);
            if ($result->object !== null) {
                return $result->object;
            }
            $error = $result->error ?? throw new \LogicException('a failed attempt carries an error');
            if ($attempt >= $this->config->maxRetries || ! Retry::isRetryable($result->outcome)) {
                throw $error;
            }
            ($this->deps->sleep)(Retry::delayMs($attempt, $result->retryAfterSec, $this->deps->random));
        }
    }

    private function idempotencyKeyFor(RequestSpec $spec): string
    {
        return $spec->idempotencyKey === null ? ($this->deps->uuid)() : IdempotencyKey::parse($spec->idempotencyKey)->value;
    }

    /** One request plus parsing. Never throws a client error: failures come back as an Attempt. */
    private function send(RequestSpec $spec, ?string $key): Attempt
    {
        try {
            $response = $this->config->httpClient->sendRequest($this->buildRequest($spec, $key));
            $text = (string) $response->getBody();
        } catch (ClientExceptionInterface $e) {
            return Attempt::failed($this->connectionError($e, $key), Outcome::network(), null);
        }

        return $this->parse($response, $text, $key);
    }

    private function buildRequest(RequestSpec $spec, ?string $key): RequestInterface
    {
        $body = $spec->method === 'POST'
            ? json_encode($spec->body === null || $spec->body === [] ? new stdClass : $spec->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
            : null;

        return new Request($spec->method, $this->buildUrl($spec), $this->buildHeaders($body !== null, $key), $body);
    }

    private function buildUrl(RequestSpec $spec): string
    {
        $query = http_build_query(array_filter($spec->query, fn (string|int|null $value): bool => $value !== null));

        return $this->config->baseUrl.self::API_PREFIX.$spec->path.($query === '' ? '' : '?'.$query);
    }

    /** @return array<string, string> */
    private function buildHeaders(bool $hasBody, ?string $key): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->config->apiKey->value(),
            'Accept' => 'application/json',
            'User-Agent' => Version::userAgent(),
        ];
        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $headers;
    }

    private function parse(ResponseInterface $response, string $text, ?string $key): Attempt
    {
        $status = $response->getStatusCode();
        $requestId = self::header($response, 'X-Request-ID');
        $parsed = json_decode($text, true);
        $success = $status >= 200 && $status < 300;
        if ($success && is_array($parsed) && str_starts_with(ltrim($text), '{')) {
            /** @var array<string, mixed> $parsed */
            return Attempt::succeeded(new ZenyObject($parsed, $this->meta($response, $key)));
        }
        $retryAfterSec = Retry::parseRetryAfter(self::header($response, 'Retry-After'), ($this->deps->now)());
        $error = $success
            ? self::unexpectedResponse($status, $requestId, $key)
            : MapError::map($status, $parsed, $requestId, $retryAfterSec, $key);
        $code = $error->code === '' ? 'unexpected_response' : $error->code;

        return Attempt::failed($error, Outcome::response($status, $code), $retryAfterSec);
    }

    private function meta(ResponseInterface $response, ?string $key): ResponseMeta
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values($values);
        }

        return new ResponseMeta(
            $response->getStatusCode(),
            self::header($response, 'X-Request-ID'),
            self::header($response, 'Idempotent-Replayed') === 'true',
            $key,
            $headers,
        );
    }

    private static function header(ResponseInterface $response, string $name): ?string
    {
        return $response->hasHeader($name) ? $response->getHeaderLine($name) : null;
    }

    /** A 2xx that is not a JSON object is a contract break, not a transient failure. */
    private static function unexpectedResponse(int $status, ?string $requestId, ?string $key): ApiError
    {
        return new ApiError(
            'api_error',
            'unexpected_response',
            'The API returned a response that was not a JSON object.',
            requestId: $requestId,
            statusCode: $status,
            idempotencyKey: $key,
        );
    }

    /** No previous exception is chained: the client's may quote the bearer header. */
    private function connectionError(ClientExceptionInterface $e, ?string $key): ZenyError
    {
        $timedOut = ($e instanceof ConnectException && ($e->getHandlerContext()['errno'] ?? null) === self::CURL_TIMEOUT)
            || stripos($e->getMessage(), 'timed out') !== false;

        return new ConnectionError($timedOut ? 'timeout' : 'network_error', self::UNKNOWN_OUTCOME_MESSAGE, $key);
    }
}
