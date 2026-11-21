<?php

declare(strict_types=1);

// Guards drift: each README php block must be a file in examples/, which PHPStan checks.
const EXAMPLE_HEADER = "<?php\n\ndeclare(strict_types=1);\n\n";

/** @return list<string> the README's php blocks */
function readmePhpBlocks(): array
{
    preg_match_all('/```php\n(.*?)\n```/s', (string) file_get_contents(__DIR__.'/../README.md'), $matches);

    return $matches[1];
}

/** @return list<string> each top-level example file without its header */
function exampleSources(): array
{
    $files = glob(__DIR__.'/../examples/*.php') ?: [];

    return array_map(fn (string $file): string => rtrim(substr((string) file_get_contents($file), strlen(EXAMPLE_HEADER)), "\n"), $files);
}

test('the README has one php block per example file', function (): void {
    expect(count(readmePhpBlocks()))->toBe(count(exampleSources()));
});

test('every README php block equals an example file', function (): void {
    expect(array_diff(readmePhpBlocks(), exampleSources()))->toBe([]);
});

test('every example file starts with the strict-types header', function (): void {
    foreach (glob(__DIR__.'/../examples/*.php') ?: [] as $file) {
        expect(str_starts_with((string) file_get_contents($file), EXAMPLE_HEADER))->toBeTrue();
    }
});
