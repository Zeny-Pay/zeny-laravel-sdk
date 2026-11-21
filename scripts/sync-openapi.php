<?php

declare(strict_types=1);

/*
 * Copies the Merchant API contract from a local monorepo checkout, or fetches
 * it with `gh api` when there is none: the same two sources as the Node SDK.
 * Usage: composer sync:openapi [monorepo-path]
 */

const SOURCE = 'apps/backend/gen/openapi/merchant.v1.json';
const TARGET = __DIR__.'/../openapi/merchant.v1.json';

function fromLocalMonorepo(string $root): ?string
{
    $file = $root.'/'.SOURCE;

    return is_file($file) ? (string) file_get_contents($file) : null;
}

function fromGitHub(): string
{
    $command = 'gh api -H '.escapeshellarg('Accept: application/vnd.github.raw').' '
        .escapeshellarg('repos/Zeny-Pay/zeny-monorepo/contents/'.SOURCE.'?ref=main');
    exec($command, $lines, $status);
    if ($status !== 0) {
        throw new RuntimeException('gh api failed');
    }

    return implode("\n", $lines)."\n";
}

function assertOpenApi(string $spec): void
{
    $parsed = json_decode($spec, true);
    if (! is_array($parsed) || ! is_array($parsed['paths'] ?? null)) {
        throw new RuntimeException('sync-openapi: source is not an OpenAPI document with a paths object');
    }
}

$argument = $argv[1] ?? null;
$roots = is_string($argument) ? [$argument] : [__DIR__.'/../../zeny-monorepo', __DIR__.'/../../zeny'];
$spec = null;
foreach ($roots as $root) {
    $spec ??= fromLocalMonorepo($root);
}
$spec ??= fromGitHub();
assertOpenApi($spec);
if (! is_dir(dirname(TARGET))) {
    mkdir(dirname(TARGET), 0777, true);
}
file_put_contents(TARGET, $spec);
echo 'wrote '.realpath(TARGET).PHP_EOL;
