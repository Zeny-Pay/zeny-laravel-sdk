<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Throwable;
use Zeny\Domain\Errors\ZenyError;
use Zeny\Infrastructure\Config;
use Zeny\Infrastructure\Http\Psr18Transport;
use Zeny\Infrastructure\Http\RequestSpec;
use Zeny\Infrastructure\Http\TransportDeps;

/**
 * A Psr18Transport over a Guzzle MockHandler, with scripted responses,
 * recorded requests and recorded sleeps. No real network, clock or randomness.
 */
final class TransportHarness
{
    public const KEY = 'sk_test_supersecretkey123';

    public readonly Psr18Transport $transport;

    /** @var list<RequestInterface> every request sent, in order */
    public array $requests = [];

    /** @var list<int> */
    public array $sleeps = [];

    /** @param  list<Response|Throwable>  $steps */
    public function __construct(array $steps, int $maxRetries = 2)
    {
        $stack = HandlerStack::create(new MockHandler($steps));
        $stack->push(Middleware::tap(function (RequestInterface $request): void {
            $this->requests[] = $request;
        }));
        $client = new Client(['handler' => $stack, 'allow_redirects' => false, 'http_errors' => false]);
        $config = Config::resolve(self::KEY, ['httpClient' => $client, 'maxRetries' => $maxRetries]);
        $this->transport = new Psr18Transport($config, new TransportDeps(
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
            random: fn (): float => 1.0,
            uuid: fn (): string => 'uuid-1',
            now: fn (): int => 0,
        ));
    }

    /**
     * A JSON response; an empty array is sent as `{}`, as the API would.
     *
     * @param  array<string, string>  $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): Response
    {
        return new Response($status, $headers, (string) json_encode($body === [] ? new \stdClass : $body));
    }

    public function request(int $index): RequestInterface
    {
        return $this->requests[$index] ?? throw new \OutOfBoundsException("no request {$index}");
    }

    /** Runs the spec and returns the error it throws; fails the test when it does not throw. */
    public function caught(RequestSpec $spec): ZenyError
    {
        try {
            $this->transport->request($spec);
        } catch (ZenyError $error) {
            return $error;
        }
        throw new \RuntimeException('expected the request to throw');
    }
}
