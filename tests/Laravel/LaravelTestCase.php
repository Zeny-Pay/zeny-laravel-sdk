<?php

declare(strict_types=1);

namespace Zeny\Tests\Laravel;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Zeny\Laravel\ZenyServiceProvider;

/** Boots a bare Laravel app with only this package's provider and a test key. */
abstract class LaravelTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ZenyServiceProvider::class];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        config(['zeny.secret_key' => 'sk_test_laravel', 'zeny.webhook_secret' => 'whsec_test_secret']);
    }

    /** The booted app; Testbench types `$app` as nullable because it is unset after tearDown. */
    protected function laravel(): Application
    {
        return $this->app ?? throw new \LogicException('The application is not booted.');
    }
}
