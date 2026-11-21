<?php

declare(strict_types=1);

namespace Zeny\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Zeny\Laravel\Middleware\VerifyZenyWebhook;
use Zeny\Zeny;

/**
 * Registers the client as a lazy singleton built from `config/zeny.php`, so a
 * missing key fails on first use, not while the app boots (artisan commands
 * and deploy steps keep working without it).
 */
final class ZenyServiceProvider extends ServiceProvider
{
    /** Container key of an optional PSR-18 client the SDK should send through. */
    public const HTTP_CLIENT = 'zeny.http_client';

    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'zeny');
        $this->app->singleton(Zeny::class, fn (Application $app): Zeny => self::client($app));
        $this->app->singleton(ZenyApi::class, fn (Application $app): ZenyApi => new ClientApi($app->make(Zeny::class)));
    }

    public function boot(): void
    {
        $this->publishes([self::configPath() => $this->app->configPath('zeny.php')], 'zeny-config');
        $router = $this->app->make('router');
        if ($router instanceof Router) {
            $router->aliasMiddleware('zeny.webhook', VerifyZenyWebhook::class);
        }
    }

    private static function client(Application $app): Zeny
    {
        $config = $app->make('config');
        if (! $config instanceof Repository) {
            throw new \LogicException('The config repository is not bound.');
        }
        $options = array_filter([
            'baseUrl' => $config->get('zeny.base_url'),
            'maxRetries' => self::number($config->get('zeny.max_retries')),
            'timeoutMs' => self::number($config->get('zeny.timeout_ms')),
            'httpClient' => $app->bound(self::HTTP_CLIENT) ? $app->make(self::HTTP_CLIENT) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return new Zeny($config->get('zeny.secret_key'), $options);
    }

    /**
     * env() hands numbers over as strings; turn a numeric string into a number
     * and leave anything else for the client to reject loudly.
     */
    private static function number(mixed $value): mixed
    {
        return is_string($value) && is_numeric($value) ? $value + 0 : $value;
    }

    private static function configPath(): string
    {
        return dirname(__DIR__, 2).'/config/zeny.php';
    }
}
