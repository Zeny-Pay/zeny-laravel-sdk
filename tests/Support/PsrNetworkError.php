<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** A PSR-18 network error as a non-Guzzle client (Symfony HttpClient, Buzz) throws it. */
final class PsrNetworkError extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
