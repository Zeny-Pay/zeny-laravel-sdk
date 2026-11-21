<?php

declare(strict_types=1);

namespace Zeny\Domain\Webhook;

use Closure;
use InvalidArgumentException;
use JsonException;
use stdClass;
use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Generated\MerchantV1;

/**
 * The webhook verification rules, free of crypto and clock: both come in as
 * arguments, so every branch is testable with fixed bytes and a fixed time.
 *
 * @phpstan-import-type ZenyEvent from MerchantV1
 */
final class Webhook
{
    private const SIGNATURE_HEADER = 'webhook-signature';

    private const TIMESTAMP_HEADER = 'webhook-timestamp';

    private const SIGNATURE_PREFIX = 'sha256=';

    private const DEFAULT_TOLERANCE_SEC = 300;

    /**
     * Verifies a webhook through `$verifier`, reading the clock from `$now`
     * (milliseconds), and returns its event. Throws SignatureVerificationError
     * with the code `signature_missing`, `signature_malformed` (also a body that
     * is not a string), `signature_mismatch`, `timestamp_out_of_tolerance` (also
     * a non-numeric timestamp or a non-finite clock) or `invalid_payload`. An
     * empty secret or a `toleranceSec` that is not a finite number >= 0 is an
     * InvalidArgumentException. An unknown event `type` passes through.
     *
     * @param  Closure(): (int|float)  $now
     * @param  mixed  $rawBody  the request body, byte for byte
     * @param  mixed  $headers  header name => value or list of values, any case: an
     *                          array, or anything iterable such as a Laravel
     *                          `$request->headers` (Symfony HeaderBag)
     * @param  array<string, mixed>  $options  `toleranceSec` (default 300)
     * @return ZenyEvent
     */
    public static function constructEventWith(HmacVerifier $verifier, Closure $now, mixed $rawBody, mixed $headers, #[\SensitiveParameter] mixed $secret, array $options = []): array
    {
        [$body, $secret, $toleranceSec] = self::checkInputs($rawBody, $secret, $options);
        $timestamp = self::header($headers, self::TIMESTAMP_HEADER);
        $signature = self::header($headers, self::SIGNATURE_HEADER);
        self::checkTimestamp($timestamp, $now(), $toleranceSec);
        if (! $verifier->verify($secret, $timestamp.'.'.$body, self::parseSignature($signature))) {
            throw self::fail('signature_mismatch', 'The webhook signature does not match.');
        }

        return self::parseEvent($body);
    }

    /**
     * Rejects unusable inputs before any crypto.
     *
     * @param  array<string, mixed>  $options
     * @return array{string, string, int|float}
     */
    private static function checkInputs(mixed $rawBody, #[\SensitiveParameter] mixed $secret, array $options): array
    {
        if (! is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('constructEvent needs the webhook secret (whsec_...) as a string.');
        }
        $toleranceSec = $options['toleranceSec'] ?? self::DEFAULT_TOLERANCE_SEC;
        if ((! is_int($toleranceSec) && ! is_float($toleranceSec)) || ! is_finite((float) $toleranceSec) || $toleranceSec < 0) {
            throw new InvalidArgumentException('toleranceSec must be a finite number of seconds, 0 or more.');
        }
        if (! is_string($rawBody)) {
            throw self::fail('signature_malformed', 'Pass the raw request body as a string (in Laravel, $request->getContent()), not a parsed array.');
        }

        return [$rawBody, $secret, $toleranceSec];
    }

    /** Case-insensitive lookup over an array or any iterable; for a list the first element wins. */
    private static function header(mixed $headers, string $name): string
    {
        $value = null;
        foreach (is_iterable($headers) ? $headers : [] as $key => $candidate) {
            if (is_string($key) && strtolower($key) === $name) {
                $value = is_array($candidate) ? ($candidate[array_key_first($candidate) ?? 0] ?? null) : $candidate;
                break;
            }
        }
        if (! is_string($value) || $value === '') {
            throw self::fail('signature_missing', "The {$name} header is missing or empty.");
        }

        return $value;
    }

    /** Rejects a timestamp that is not plain digits or lies outside the tolerance window. */
    private static function checkTimestamp(string $timestamp, int|float $nowMs, int|float $toleranceSec): void
    {
        if (preg_match('/^\d+\z/', $timestamp) !== 1) {
            throw self::fail('timestamp_out_of_tolerance', 'The webhook timestamp is not a Unix time in seconds.');
        }
        // Fail closed: NaN compares false against everything and would accept any timestamp.
        $age = abs($nowMs / 1000 - (float) $timestamp);
        if (! is_finite($age) || $age > $toleranceSec) {
            throw self::fail('timestamp_out_of_tolerance', 'The webhook timestamp is outside the tolerance window.');
        }
    }

    /** `sha256=<64 hex>` to 32 raw bytes. Uppercase hex is accepted. */
    private static function parseSignature(string $header): string
    {
        $hex = str_starts_with($header, self::SIGNATURE_PREFIX) ? strtolower(substr($header, strlen(self::SIGNATURE_PREFIX))) : '';
        $bytes = preg_match('/^[0-9a-f]{64}\z/', $hex) === 1 ? hex2bin($hex) : false;
        if ($bytes === false) {
            throw self::fail('signature_malformed', 'The webhook signature must be sha256= and 64 hex characters.');
        }

        return $bytes;
    }

    /**
     * Parses a verified body; anything but a JSON object is `invalid_payload`.
     *
     * @return ZenyEvent
     */
    private static function parseEvent(string $body): array
    {
        try {
            // Decoded once as objects, because `{}` and `[]` both become [] as arrays.
            $isObject = json_decode($body, false, 512, JSON_THROW_ON_ERROR) instanceof stdClass;
            $event = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw self::fail('invalid_payload', 'The webhook body is not valid JSON.', $error);
        }
        if (! $isObject || ! is_array($event)) {
            throw self::fail('invalid_payload', 'The webhook body is not a JSON object.');
        }

        /** @var ZenyEvent $event the signature vouches for the sender; the shape is the contract's */
        return $event;
    }

    private static function fail(string $code, string $message, ?\Throwable $previous = null): SignatureVerificationError
    {
        return new SignatureVerificationError('signature_verification', $code, $message, previous: $previous);
    }
}
