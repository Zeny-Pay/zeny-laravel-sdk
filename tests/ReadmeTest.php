<?php

declare(strict_types=1);

// Guards drift: each php block in README.md and GUIDE.md must be a file in examples/, which PHPStan checks.
const EXAMPLE_HEADER = "<?php\n\ndeclare(strict_types=1);\n\n";

/** @return list<string> the php blocks of README.md and GUIDE.md, in that order */
function readmePhpBlocks(): array
{
    $blocks = [];
    foreach (['README.md', 'GUIDE.md'] as $document) {
        preg_match_all('/```php\n(.*?)\n```/s', (string) file_get_contents(__DIR__.'/../'.$document), $matches);
        $blocks = [...$blocks, ...$matches[1]];
    }

    return $blocks;
}

/** @return list<string> each top-level example file without its header */
function exampleSources(): array
{
    $files = glob(__DIR__.'/../examples/*.php') ?: [];

    return array_map(fn (string $file): string => rtrim(substr((string) file_get_contents($file), strlen(EXAMPLE_HEADER)), "\n"), $files);
}

test('README and GUIDE have one php block per example file', function (): void {
    expect(count(readmePhpBlocks()))->toBe(count(exampleSources()));
});

test('every README and GUIDE php block equals an example file', function (): void {
    expect(array_diff(readmePhpBlocks(), exampleSources()))->toBe([]);
});

test('every example file starts with the strict-types header', function (): void {
    foreach (glob(__DIR__.'/../examples/*.php') ?: [] as $file) {
        expect(str_starts_with((string) file_get_contents($file), EXAMPLE_HEADER))->toBeTrue();
    }
});
