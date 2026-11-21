<?php

declare(strict_types=1);

/*
 * Regenerates src/Generated/MerchantV1.php from openapi/merchant.v1.json.
 * Usage: composer generate
 */

use Zeny\Scripts\Json;
use Zeny\Scripts\PhpStanTypeWriter;

require __DIR__.'/../vendor/autoload.php';

$target = __DIR__.'/../src/Generated/MerchantV1.php';
if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0777, true);
}
$spec = Json::decodeFile(__DIR__.'/../openapi/merchant.v1.json');
file_put_contents($target, (new PhpStanTypeWriter($spec))->write());
echo 'wrote '.realpath($target).PHP_EOL;
