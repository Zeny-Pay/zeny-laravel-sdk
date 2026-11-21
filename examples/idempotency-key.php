<?php

declare(strict_types=1);

use Zeny\Domain\RequestOptions;
use Zeny\Laravel\Facades\Zeny;

// Your own key: derive it from the order so a restarted worker repeats the same intent.
$payment = Zeny::payments()->create(
    ['reference' => 'order-123', 'amount' => ['value' => '150000', 'currency' => 'IDR']],
    new RequestOptions(idempotencyKey: 'order-123:create'),
);

echo $payment->toArray()['id'];
