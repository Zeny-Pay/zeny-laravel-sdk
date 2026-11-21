<?php

declare(strict_types=1);

namespace Zeny\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Zeny\Laravel\Middleware\VerifyZenyWebhook;
use Zeny\Laravel\ZenyServiceProvider;
use Zeny\Zeny;

final class ZenyServiceProviderTest extends LaravelTestCase
{
    public function test_the_package_config_is_merged(): void
    {
        $this->assertSame(2, config('zeny.max_retries'));
    }

    public function test_the_config_publishes_under_the_zeny_config_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(ZenyServiceProvider::class, 'zeny-config');

        $this->assertSame([$this->laravel()->configPath('zeny.php')], array_values($paths));
    }

    public function test_the_client_is_not_built_at_boot(): void
    {
        $this->assertFalse($this->laravel()->resolved(Zeny::class));
    }

    public function test_the_client_is_a_singleton(): void
    {
        $this->assertSame($this->laravel()->make(Zeny::class), $this->laravel()->make(Zeny::class));
    }

    public function test_a_missing_key_throws_when_the_client_is_first_resolved(): void
    {
        config(['zeny.secret_key' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->laravel()->make(Zeny::class);
    }

    public function test_numeric_strings_from_env_are_accepted(): void
    {
        config(['zeny.max_retries' => '0', 'zeny.timeout_ms' => '1500.5']);

        $this->assertInstanceOf(Zeny::class, $this->laravel()->make(Zeny::class));
    }

    public function test_a_non_numeric_retry_count_throws(): void
    {
        config(['zeny.max_retries' => 'three']);

        $this->expectException(InvalidArgumentException::class);
        $this->laravel()->make(Zeny::class);
    }

    public function test_the_zeny_webhook_middleware_alias_is_registered(): void
    {
        $router = $this->laravel()->make(Router::class);

        $this->assertSame(VerifyZenyWebhook::class, $router->getMiddleware()['zeny.webhook'] ?? null);
    }

    public function test_a_bound_http_client_with_a_timeout_throws(): void
    {
        $this->laravel()->instance(ZenyServiceProvider::HTTP_CLIENT, new Client);
        config(['zeny.timeout_ms' => '1500']);

        $this->expectException(InvalidArgumentException::class);
        $this->laravel()->make(Zeny::class);
    }

    public function test_calls_use_the_bound_http_client_and_the_configured_base_url(): void
    {
        $uris = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"id":"pay_1"}')]));
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$uris): void {
            $uris[] = (string) $request->getUri();
        }));
        $this->laravel()->instance(ZenyServiceProvider::HTTP_CLIENT, new Client(['handler' => $stack]));
        config(['zeny.base_url' => 'https://api.example.test']);

        $this->laravel()->make(Zeny::class)->payments->retrieve('pay_1');

        $this->assertSame(['https://api.example.test/api/merchant/v1/payments/pay_1'], $uris);
    }
}
