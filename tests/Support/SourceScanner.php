<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads the SDK sources with PHP's tokenizer, so the architecture test checks
 * real `use` statements and function calls rather than text that happens to
 * look like one.
 */
final class SourceScanner
{
    /** @return list<string> absolute paths of the PHP files under src/{$layer} */
    public static function files(string $layer): array
    {
        $dir = dirname(__DIR__, 2).'/src/'.$layer;
        if (! is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<string> every name a `use` statement in the layer imports */
    public static function imports(string $layer): array
    {
        $imports = [];
        foreach (self::files($layer) as $file) {
            array_push($imports, ...self::importsOf($file));
        }

        return $imports;
    }

    /** @return list<string> the names `use` statements in one file import */
    public static function importsOf(string $file): array
    {
        preg_match_all('/^use\s+(?:function\s+|const\s+)?([^;\s]+)/m', (string) file_get_contents($file), $matches);

        return $matches[1];
    }

    /**
     * @param  list<string>  $names
     * @return list<string> "file calls name" for each forbidden global call
     */
    public static function calls(string $layer, array $names): array
    {
        $found = [];
        foreach (self::files($layer) as $file) {
            foreach (self::callsOf($file) as $call) {
                if (in_array(strtolower($call), $names, true)) {
                    $found[] = basename($file).' calls '.$call;
                }
            }
        }

        return $found;
    }

    /** @return list<string> names called as plain functions: `name(` not after `->`, `::`, `new` or `function` */
    public static function callsOf(string $file): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize((string) file_get_contents($file)),
            fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));
        $calls = [];
        foreach ($tokens as $i => $token) {
            $next = $tokens[$i + 1] ?? null;
            $previous = $tokens[$i - 1] ?? null;
            if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && $next?->text === '('
                && ! $previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_FUNCTION])) {
                $calls[] = ltrim($token->text, '\\');
            }
        }

        return $calls;
    }
}
