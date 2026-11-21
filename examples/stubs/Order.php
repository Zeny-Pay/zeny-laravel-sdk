<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Stand-in for the merchant's own order model, so the examples type-check.
 * Not part of the package.
 */
final class Order
{
    public int $id = 1042;

    /** The order total as a decimal string, as the API expects amounts. */
    public string $total = '150000';

    /** @var array<string, string> */
    private array $attributes = [];

    /**
     * Saves the given columns, as an Eloquent model does.
     *
     * @param  array<string, string>  $attributes
     */
    public function update(array $attributes): bool
    {
        $this->attributes = [...$this->attributes, ...$attributes];

        return true;
    }

    /** Marks the order whose payment `reference` this is as paid. */
    public static function markPaid(string $reference): void {}

    /** Marks the order that holds this payment link id as paid. */
    public static function markPaidByLink(string $paymentLinkId): void {}
}
