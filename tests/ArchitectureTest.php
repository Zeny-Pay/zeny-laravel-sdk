<?php

declare(strict_types=1);

use Zeny\Tests\Support\SourceScanner;

/*
 * Layer rules (spec section 3, the same as zeny-node's architecture.test.ts):
 * Domain imports only Domain and Generated; Infrastructure never imports
 * Illuminate; Zeny.php never imports Illuminate. Each layer must hold a class,
 * so a broken path cannot make the rules pass vacuously.
 */

const DOMAIN_FORBIDDEN_CALLS = [
    'curl_init', 'curl_exec', 'fsockopen', 'stream_socket_client', 'file_get_contents', 'fopen',
    'hash_hmac', 'hash', 'random_bytes', 'random_int', 'mt_rand', 'rand', 'uniqid',
    'time', 'microtime', 'hrtime', 'date', 'usleep', 'sleep',
];

foreach (['Domain', 'Generated', 'Infrastructure'] as $layer) {
    test("the {$layer} layer holds at least one class", function () use ($layer): void {
        expect(SourceScanner::files($layer))->not->toBeEmpty();
    });
}

test('the scanners see real use statements and calls', function (): void {
    expect(SourceScanner::importsOf(__FILE__))->toContain('Zeny\\Tests\\Support\\SourceScanner')
        ->and(SourceScanner::callsOf(__FILE__))->toContain('array_filter');
});

test('Domain imports only Domain, Generated and PHP built-ins', function (): void {
    $imports = SourceScanner::imports('Domain');

    $outside = array_filter($imports, fn (string $import): bool => str_contains($import, '\\')
        && ! str_starts_with($import, 'Zeny\\Domain\\')
        && ! str_starts_with($import, 'Zeny\\Generated\\'));

    expect(array_values($outside))->toBe([]);
});

test('Domain calls no network, crypto, clock or timer function', function (): void {
    expect(SourceScanner::calls('Domain', DOMAIN_FORBIDDEN_CALLS))->toBe([]);
});

test('Infrastructure imports only Domain, Generated, Infrastructure, PSR and Guzzle', function (): void {
    $imports = SourceScanner::imports('Infrastructure');

    $allowed = ['Zeny\\Domain\\', 'Zeny\\Generated\\', 'Zeny\\Infrastructure\\', 'Psr\\', 'GuzzleHttp\\'];
    $outside = array_filter($imports, fn (string $import): bool => str_contains($import, '\\')
        && array_filter($allowed, fn (string $prefix): bool => str_starts_with($import, $prefix)) === []);

    expect(array_values($outside))->toBe([]);
});

arch('Infrastructure never imports Illuminate', function (): void {
    expect('Zeny\Infrastructure')->not->toUse('Illuminate');
});

arch('Domain never imports a package or another layer', function (): void {
    expect('Zeny\Domain')->not->toUse(['Illuminate', 'GuzzleHttp', 'Psr', 'Zeny\Infrastructure', 'Zeny\Laravel']);
});

arch('the core never depends on the Laravel adapter', function (): void {
    expect(['Zeny\Domain', 'Zeny\Infrastructure', 'Zeny\Generated'])->not->toUse('Zeny\Laravel');
});

arch('no debugging calls ship', function (): void {
    expect('Zeny')->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);
});
