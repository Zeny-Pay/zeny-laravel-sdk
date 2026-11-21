<?php

declare(strict_types=1);

namespace Zeny\Tests\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Zeny\Laravel\Middleware\VerifyZenyWebhook;
use Zeny\Tests\Support\WebhookFixture as F;

final class VerifyZenyWebhookTest extends LaravelTestCase
{
    private int $handled = 0;

    /** @param  Router  $router */
    protected function defineRoutes($router): void
    {
        $router->post('/webhooks', function (Request $request): array {
            $this->handled++;

            return ['event' => $request->attributes->get('zenyEvent')];
        })->middleware('zeny.webhook');
        $router->post('/typed', fn (Request $request): array => ['id' => VerifyZenyWebhook::event($request)['id']])->middleware('zeny.webhook');
        $router->post('/unguarded', fn (Request $request): array => VerifyZenyWebhook::event($request));
    }

    /**
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function deliver(string $body, array $headers, string $uri = '/webhooks'): TestResponse
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], $body);
    }

    public function test_a_signed_request_reaches_the_route_with_the_event(): void
    {
        $this->deliver(F::body(), F::headers(time()))->assertOk()->assertJsonPath('event.id', 'evt_1');
    }

    public function test_a_bad_signature_gets_400(): void
    {
        $this->deliver(F::body(), ['webhook-signature' => 'sha256='.str_repeat('0', 64)] + F::headers(time()))->assertStatus(400);
    }

    public function test_the_400_body_carries_the_error_code(): void
    {
        $this->deliver(F::body(), [])->assertJsonPath('error.code', 'signature_missing');
    }

    public function test_a_rejected_request_never_reaches_the_route(): void
    {
        $this->deliver('{"forged":true}', F::headers(time()));

        $this->assertSame(0, $this->handled);
    }

    public function test_a_missing_webhook_secret_throws_instead_of_accepting(): void
    {
        config(['zeny.webhook_secret' => null]);
        $this->withoutExceptionHandling();

        $this->expectException(InvalidArgumentException::class);
        $this->deliver(F::body(), F::headers(time()));
    }

    public function test_event_returns_the_verified_event(): void
    {
        $this->deliver(F::body(), F::headers(time()), '/typed')->assertJsonPath('id', 'evt_1');
    }

    public function test_event_throws_on_a_route_without_the_middleware(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(LogicException::class);
        $this->deliver(F::body(), F::headers(time()), '/unguarded');
    }
}
