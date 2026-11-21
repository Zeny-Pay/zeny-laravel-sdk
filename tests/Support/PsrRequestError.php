<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** A PSR-18 request error as a non-Guzzle client (Symfony HttpClient, Buzz) throws it. */
final class PsrRequestError extends \RuntimeException implements RequestExceptionInterface
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
