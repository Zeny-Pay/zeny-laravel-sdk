<?php

declare(strict_types=1);

namespace Zeny\Laravel\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Zeny;

/**
 * Verifies a webhook before the route runs and puts the event on the request
 * as the `zenyEvent` attribute. A bad signature gets 400; anything else,
 * including a missing webhook secret, is thrown so it is never accepted.
 */
final class VerifyZenyWebhook
{
    public function __construct(private readonly Repository $config) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = Zeny::webhooks()->constructEvent(
                $request->getContent(),
                $request->headers->all(),
                $this->config->get('zeny.webhook_secret'),
            );
        } catch (SignatureVerificationError $error) {
            return new JsonResponse(['error' => ['type' => $error->type, 'code' => $error->code, 'message' => $error->getMessage()]], 400);
        }
        $request->attributes->set('zenyEvent', $event);

        return $next($request);
    }
}
