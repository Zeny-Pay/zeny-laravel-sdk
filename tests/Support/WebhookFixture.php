<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Infrastructure\Webhooks;

/**
 * zeny-node's webhook vectors (test/webhook.test.ts): the same secret, clock
 * and event, signed here with hash_hmac, independently of the verifier.
 */
final class WebhookFixture
{
    public const SECRET = 'whsec_test_secret';

    public const NOW = 1800000000;

    public const EVENT = [
        'id' => 'evt_1',
        'object' => 'event',
        'type' => 'payment.paid',
        'apiVersion' => 'v1',
        'createdAt' => '2026-11-21T00:00:00Z',
        'mode' => 'test',
        'resourceVersion' => 3,
        'data' => ['object' => ['id' => 'pay_1']],
    ];

    public static function body(): string
    {
        return (string) json_encode(self::EVENT, JSON_UNESCAPED_SLASHES);
    }

    public static function sign(int|string $timestamp, string $payload, string $secret = self::SECRET): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /** @return array<string, string> */
    public static function headers(int|string $timestamp = self::NOW, ?string $payload = null): array
    {
        return [
            'webhook-signature' => self::sign($timestamp, $payload ?? self::body()),
            'webhook-timestamp' => (string) $timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function run(mixed $body, mixed $headers, array $options = [], mixed $secret = self::SECRET): array
    {
        return (new Webhooks)->constructEvent($body, $headers, $secret, $options + ['now' => fn (): int => self::NOW * 1000]);
    }

    /** @param  array<string, mixed>  $options */
    public static function caught(mixed $body, mixed $headers, array $options = [], mixed $secret = self::SECRET): SignatureVerificationError
    {
        try {
            self::run($body, $headers, $options, $secret);
        } catch (SignatureVerificationError $error) {
            return $error;
        }
        throw new \RuntimeException('expected constructEvent to throw');
    }

    /** @param  array<string, mixed>  $options */
    public static function code(mixed $body, mixed $headers, array $options = [], mixed $secret = self::SECRET): string
    {
        return self::caught($body, $headers, $options, $secret)->code;
    }
}
