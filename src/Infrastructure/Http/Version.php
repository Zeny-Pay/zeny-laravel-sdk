<?php

declare(strict_types=1);

namespace Zeny\Infrastructure\Http;

/**
 * The SDK version sent in the User-Agent header. It lives in code, not in
 * composer.json, because Packagist reads versions from tags and
 * `composer validate --strict` refuses a `version` field. The release workflow
 * checks that the tag equals this value.
 */
final class Version
{
    public const VALUE = '1.0.0';

    public static function userAgent(): string
    {
        return 'zeny-laravel-sdk/'.self::VALUE;
    }
}
