<?php

declare(strict_types=1);

namespace Zeny\Scripts;

/**
 * Writes src/Generated/MerchantV1.php from the Merchant API contract: one
 * `@phpstan-type` per component schema (prefixed `S_` so names never clash),
 * plus the friendly aliases the SDK's public signatures use.
 */
final class PhpStanTypeWriter
{
    private const PUBLIC_ALIASES = [
        'Money' => 'type.v1.Money',
        'Payment' => 'merchant.payment.v1.Payment',
        'PaymentAttempt' => 'merchant.payment.v1.PaymentAttempt',
        'PaymentLink' => 'merchant.payment.v1.PaymentLink',
        'PaymentLinkList' => 'merchant.payment.v1.ListPaymentLinksResponse',
        'Payout' => 'merchant.payout.v1.Payout',
        'Dispute' => 'merchant.payment.v1.Dispute',
        'DisputeList' => 'merchant.payment.v1.ListDisputesResponse',
        'TestFunds' => 'merchant.balance.v1.TestFunds',
        'CreatePaymentParams' => 'merchant.payment.v1.CreatePaymentRequest',
        'CreatePaymentLinkParams' => 'merchant.payment.v1.CreatePaymentLinkRequest',
        'CreatePayoutParams' => 'merchant.payout.v1.CreatePayoutRequest',
        'CreateTestFundsParams' => 'merchant.balance.v1.CreateTestFundsRequest',
        'ApiErrorBody' => 'Error',
        'PaymentEvent' => 'merchant.payment.v1.PaymentEvent',
        'PaymentAttemptEvent' => 'merchant.payment.v1.PaymentAttemptEvent',
        'PayoutEvent' => 'merchant.payout.v1.PayoutEvent',
        'DisputeEvent' => 'merchant.payment.v1.DisputeEvent',
    ];

    private const ATTEMPTS_PATH = '/api/merchant/v1/payments/{id}/attempts';

    private const DISPUTES_PATH = '/api/merchant/v1/disputes';

    private const PAYMENT_LINKS_PATH = '/api/merchant/v1/payment-links';

    private readonly SchemaTyper $typer;

    /** @param array<string, mixed> $spec the decoded OpenAPI document */
    public function __construct(private readonly array $spec)
    {
        $this->typer = new SchemaTyper;
    }

    public function write(): string
    {
        $aliases = array_merge($this->schemaAliases(), $this->publicAliases(), $this->operationAliases(), $this->eventAliases());
        $lines = [];
        foreach ($aliases as $name => $type) {
            $lines[] = ' * @phpstan-type '.$name.' '.str_replace("\n", "\n * ", $type);
        }

        return "<?php\n\n// generated — do not edit\n// Source: openapi/merchant.v1.json (composer generate)\n\n"
            ."declare(strict_types=1);\n\nnamespace Zeny\\Generated;\n\n/**\n"
            ." * PHPStan shapes of the Merchant API v1 contract.\n *\n"
            .implode("\n", $lines)."\n */\nfinal class MerchantV1 {}\n";
    }

    /** @return array<string, string> */
    private function schemaAliases(): array
    {
        $schemas = $this->schemas();
        ksort($schemas);
        $aliases = [];
        foreach ($schemas as $name => $schema) {
            $aliases[SchemaTyper::aliasOf($name)] = $this->typer->type(Json::object($schema));
        }

        return $aliases;
    }

    /** @return array<string, string> */
    private function publicAliases(): array
    {
        $aliases = [];
        foreach (self::PUBLIC_ALIASES as $alias => $schemaName) {
            if (! isset($this->schemas()[$schemaName])) {
                throw new \RuntimeException("contract has no schema {$schemaName}");
            }
            $aliases[$alias] = SchemaTyper::aliasOf($schemaName);
        }

        return $aliases;
    }

    /** @return array<string, string> */
    private function operationAliases(): array
    {
        $body = Json::object($this->operation(self::ATTEMPTS_PATH, 'post')['requestBody'] ?? null);
        $schema = Json::object(Json::object(Json::object($body['content'] ?? null)['application/json'] ?? null)['schema'] ?? null);

        return [
            'CreatePaymentAttemptParams' => $this->typer->type($schema),
            'ListDisputesParams' => $this->queryShape($this->operation(self::DISPUTES_PATH, 'get')),
            'ListPaymentLinksParams' => $this->queryShape($this->operation(self::PAYMENT_LINKS_PATH, 'get')),
        ];
    }

    /** @param array<string, mixed> $operation */
    private function queryShape(array $operation): string
    {
        $properties = [];
        foreach (Json::list($operation['parameters'] ?? []) as $parameter) {
            $parameter = Json::object($parameter);
            if (($parameter['in'] ?? null) === 'query') {
                $properties[Json::string($parameter['name'] ?? '')] = $parameter['schema'] ?? [];
            }
        }

        return $this->typer->shape($properties, [], 0);
    }

    /** @return array<string, string> */
    private function eventAliases(): array
    {
        $bySchema = [];
        foreach ($this->webhooks() as $eventType => $schemaName) {
            $bySchema[$schemaName][] = $eventType;
        }
        $arms = [];
        foreach ($bySchema as $schemaName => $eventTypes) {
            $arms[] = $this->eventArm($schemaName, $eventTypes);
        }

        return [
            'ZenyEventType' => implode('|', array_map(fn (string $type): string => "'{$type}'", array_keys($this->webhooks()))),
            'ZenyEvent' => implode('|', $arms),
        ];
    }

    /**
     * One arm of the event union: the event schema with `type` narrowed to the
     * webhook keys that deliver it, so `match ($event['type'])` narrows `data`.
     *
     * @param  list<string>  $eventTypes
     */
    private function eventArm(string $schemaName, array $eventTypes): string
    {
        $schema = Json::object($this->schemas()[$schemaName] ?? null);
        $properties = Json::object($schema['properties'] ?? null);
        $properties['type'] = ['enum' => $eventTypes];
        $schema['properties'] = $properties;

        return $this->typer->type($schema);
    }

    /** @return array<string, string> webhook event type → component schema name, sorted */
    private function webhooks(): array
    {
        $events = [];
        foreach (Json::object($this->spec['webhooks'] ?? null) as $type => $item) {
            $body = Json::object(Json::object($item)['post'] ?? null)['requestBody'] ?? null;
            $schema = Json::object(Json::object(Json::object(Json::object($body)['content'] ?? null)['application/json'] ?? null)['schema'] ?? null);
            $events[$type] = substr(Json::string($schema['$ref'] ?? ''), strlen('#/components/schemas/'));
        }
        ksort($events);

        return $events;
    }

    /** @return array<string, mixed> */
    private function schemas(): array
    {
        return Json::object(Json::object($this->spec['components'] ?? null)['schemas'] ?? null);
    }

    /** @return array<string, mixed> */
    private function operation(string $path, string $method): array
    {
        $operation = Json::object(Json::object(Json::object($this->spec['paths'] ?? null)[$path] ?? null)[$method] ?? null);
        if ($operation === []) {
            throw new \RuntimeException("contract has no {$method} {$path}");
        }

        return $operation;
    }
}
