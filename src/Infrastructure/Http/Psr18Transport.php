<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Zeny\Domain\Errors\ApiError;
use Zeny\Domain\Errors\ConnectionError;
use Zeny\Domain\Errors\ZenyError;
use Zeny\Domain\IdempotencyKey;
use Zeny\Domain\JsonShape;
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
        $key = $spec->method === 'POST' && ! $spec->bodiless ? $this->idempotencyKeyFor($spec) : null;
        $body = self::encodeBody($spec);
        for ($attempt = 0; ; $attempt++) {
            $result = $this->send($spec, $body, $key);
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

    /**
     * One request plus parsing. Never throws a client error: failures come back
     * as an Attempt. Any other Throwable from building or sending the request
     * is caught too, because a header validation error quotes the header value.
     */
    private function send(RequestSpec $spec, ?string $body, ?string $key): Attempt
    {
        try {
            $request = new Request($spec->method, $this->buildUrl($spec), $this->buildHeaders($body !== null, $key), $body);
            $response = $this->config->httpClient->sendRequest($request);
            $text = (string) $response->getBody();
        } catch (\Throwable $e) {
            return Attempt::failed($this->connectionError($e, $key), Outcome::network(), null);
        }

        return $this->parse($response, $text, $key);
    }

    /** The JSON body of a POST (`{}` when there is none), or null for a GET or a bodiless action. */
    private static function encodeBody(RequestSpec $spec): ?string
    {
        if ($spec->method !== 'POST' || $spec->bodiless) {
            return null;
        }

        return json_encode(JsonShape::encode($spec->body ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
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
        if ($status >= 300 && $status < 400) {
            // As fetch with redirect "error": a redirect is never followed and the outcome is unknown.
            return Attempt::failed(new ConnectionError('network_error', self::UNKNOWN_OUTCOME_MESSAGE, $key), Outcome::network(), null);
        }
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
            : MapError::map($status, json_decode($text, false), $requestId, $retryAfterSec, $key);
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
    private function connectionError(\Throwable $e, ?string $key): ZenyError
    {
        return new ConnectionError(self::isTimeout($e) ? 'timeout' : 'network_error', self::UNKNOWN_OUTCOME_MESSAGE, $key);
    }

    /**
     * cURL's errno 28 through Guzzle, or a PSR-18 network or request error
     * whose message names a timeout ("timeout", "timed out", "Idle timeout"),
     * as Symfony HttpClient and other clients word it.
     */
    private static function isTimeout(\Throwable $e): bool
    {
        if ($e instanceof ConnectException && ($e->getHandlerContext()['errno'] ?? null) === self::CURL_TIMEOUT) {
            return true;
        }

        return ($e instanceof NetworkExceptionInterface || $e instanceof RequestExceptionInterface)
            && preg_match('/time(d)?[ -]?out/i', $e->getMessage()) === 1;
    }
}
