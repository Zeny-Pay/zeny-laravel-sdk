<?php

declare(strict_types=1);

use Zeny\Zeny;

// A white-label brand has its own host; the key prefix still picks Sandbox or Production.
$brandClient = new Zeny((string) getenv('ZENY_SECRET_KEY'), [
    'baseUrl' => 'https://api.brand.example',
    'timeoutMs' => 15000,
    'maxRetries' => 3,
]);
