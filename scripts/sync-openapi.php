<?php

declare(strict_types=1);

/*
 * Copies the Merchant API contract from a local monorepo checkout, or fetches
 * it with `gh api` when there is none: the same two sources as the Node SDK.
 * Usage: composer sync:openapi [monorepo-path] [--ref=<git-ref>]
 * With --ref the file is read from that commit or branch of the local
 * checkout (`git show <ref>:<file>`), e.g. an unmerged contract branch.
 */

const SOURCE = 'apps/backend/gen/openapi/merchant.v1.json';
const TARGET = __DIR__.'/../openapi/merchant.v1.json';

function fromLocalMonorepo(string $root): ?string
{
    $file = $root.'/'.SOURCE;

    return is_file($file) ? (string) file_get_contents($file) : null;
}

function fromGitRef(string $root, string $ref): ?string
{
    if (! is_dir($root.'/.git') && ! is_file($root.'/.git')) {
        return null;
    }
    exec('git -C '.escapeshellarg($root).' show '.escapeshellarg($ref.':'.SOURCE).' 2>/dev/null', $lines, $status);
    if ($status !== 0) {
        throw new RuntimeException("git show {$ref}:".SOURCE." failed in {$root}");
    }

    return implode("\n", $lines)."\n";
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

$arguments = array_slice($argv ?? [], 1);
$refs = array_values(array_filter($arguments, fn (string $arg): bool => str_starts_with($arg, '--ref=')));
$paths = array_values(array_filter($arguments, fn (string $arg): bool => ! str_starts_with($arg, '--ref=')));
$ref = isset($refs[0]) ? substr($refs[0], strlen('--ref=')) : null;
$roots = isset($paths[0]) ? [$paths[0]] : [__DIR__.'/../../zeny-monorepo', __DIR__.'/../../zeny'];
$spec = null;
foreach ($roots as $root) {
    $spec ??= $ref === null ? fromLocalMonorepo($root) : fromGitRef($root, $ref);
}
if ($spec === null && $ref !== null) {
    throw new RuntimeException("no local monorepo checkout to read {$ref} from");
}
$spec ??= fromGitHub();
assertOpenApi($spec);
if (! is_dir(dirname(TARGET))) {
    mkdir(dirname(TARGET), 0777, true);
}
file_put_contents(TARGET, $spec);
echo 'wrote '.realpath(TARGET).PHP_EOL;
