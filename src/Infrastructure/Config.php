<?php

declare(strict_types=1);

namespace Zeny\Infrastructure;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Zeny\Domain\ApiKey;

/**
 * Validated client settings, built once per client by `resolve()`. The key
 * stays inside an ApiKey, so dumping a Config never prints it.
 */
final class Config
{
    /** Base URL of each environment. The key prefix chooses the environment. */
    public const BASE_URLS = [
        'live' => 'https://api.usezeny.com',
        'test' => 'https://snorlax.usezeny.com',
    ];

    public const DEFAULT_MAX_RETRIES = 2;

    public const DEFAULT_TIMEOUT_MS = 30000;

    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private function __construct(
        public readonly ApiKey $apiKey,
        public readonly string $baseUrl,
        public readonly int $maxRetries,
        public readonly int|float $timeoutMs,
        public readonly ClientInterface $httpClient,
    ) {}

    /**
     * Turns the key and options into a validated config. Options: `baseUrl`,
     * `maxRetries` (default 2), `timeoutMs` (default 30000) and `httpClient`
     * (a PSR-18 client; the default is Guzzle with the timeout set and
     * redirects off). Invalid values throw InvalidArgumentException.
     *
     * @param  array<string, mixed>  $options
     */
    public static function resolve(#[\SensitiveParameter] mixed $apiKey, array $options = []): self
    {
        $key = ApiKey::parse($apiKey);
        $timeoutMs = self::timeoutMs($options['timeoutMs'] ?? self::DEFAULT_TIMEOUT_MS);

        return new self(
            $key,
            self::baseUrl($options['baseUrl'] ?? self::BASE_URLS[$key->mode]),
            self::maxRetries($options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES),
            $timeoutMs,
            self::httpClient($options['httpClient'] ?? null, $timeoutMs),
        );
    }

    /**
     * Returns `$value` without trailing slashes. https for any host, http only
     * for loopback; userinfo is refused because proxies log it next to the key.
     */
    private static function baseUrl(mixed $value): string
    {
        $parts = is_string($value) && preg_match('/[\s\\\\]/', $value) !== 1 ? parse_url($value) : false;
        $scheme = strtolower(is_array($parts) ? ($parts['scheme'] ?? '') : '');
        $host = strtolower(is_array($parts) ? ($parts['host'] ?? '') : '');
        $valid = is_string($value) && is_array($parts) && $host !== ''
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOCAL_HOSTS, true)));
        if (! $valid) {
            throw new InvalidArgumentException('baseUrl must be an https URL (http only for localhost, 127.0.0.1 or [::1])');
        }

        return rtrim($value, '/');
    }

    private static function maxRetries(mixed $value): int
    {
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException('maxRetries must be an integer of 0 or more');
        }

        return $value;
    }

    private static function timeoutMs(mixed $value): int|float
    {
        if (! (is_int($value) || is_float($value)) || ! is_finite((float) $value) || $value <= 0) {
            throw new InvalidArgumentException('timeoutMs must be a finite number greater than 0');
        }

        return $value;
    }

    private static function httpClient(mixed $client, int|float $timeoutMs): ClientInterface
    {
        if ($client === null) {
            return new Client(['timeout' => $timeoutMs / 1000, 'allow_redirects' => false, 'http_errors' => false]);
        }
        if (! $client instanceof ClientInterface) {
            throw new InvalidArgumentException('httpClient must implement Psr\Http\Client\ClientInterface');
        }

        return $client;
    }
}
