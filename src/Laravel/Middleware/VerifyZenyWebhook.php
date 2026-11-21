<?php

declare(strict_types=1);

namespace Zeny\Laravel\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Generated\MerchantV1;
use Zeny\Zeny;

/**
 * Verifies a webhook before the route runs and puts the event on the request
 * as the `zenyEvent` attribute. A bad signature gets 400; anything else,
 * including a missing webhook secret, is thrown so it is never accepted.
 *
 * @phpstan-import-type ZenyEvent from MerchantV1
 */
final class VerifyZenyWebhook
{
    /** The request attribute that holds the verified event. */
    public const ATTRIBUTE = 'zenyEvent';

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
        $request->attributes->set(self::ATTRIBUTE, $event);

        return $next($request);
    }

    /**
     * The verified event of a route behind this middleware, typed, so a
     * `match` on its `type` narrows `data.object`.
     *
     * @return ZenyEvent
     *
     * @throws LogicException when the route does not run behind the middleware
     */
    public static function event(Request $request): array
    {
        $event = $request->attributes->get(self::ATTRIBUTE);
        if (! is_array($event)) {
            throw new LogicException('No verified webhook event on this request: add the zeny.webhook middleware to the route.');
        }

        /** @var ZenyEvent $event */
        return $event;
    }
}
