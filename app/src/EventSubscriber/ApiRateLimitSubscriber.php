<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Plafonne par adresse les lectures publiques de l'API, avant tout travail (base, appel à BGG).
 * Les routes d'authentification, de compte et d'administration ont leurs propres limiteurs ou leurs droits.
 *
 * La réponse porte `X-RateLimit-*` et, au-delà du plafond, `Retry-After` : un client sérieux (l'import de
 * BG-TCG) règle son rythme dessus au lieu de buter.
 */
final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private const ATTRIBUTE = '_api_rate_limit';

    public function __construct(
        #[Autowire(service: 'limiter.catalogue')]
        private readonly RateLimiterFactoryInterface $catalogue,
        #[Autowire(service: 'limiter.search')]
        private readonly RateLimiterFactoryInterface $search,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApi,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Juste après le routeur (32) : le chemin est établi, rien d'autre n'a encore travaillé.
            KernelEvents::REQUEST => ['onRequest', 31],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $factory = $this->factoryFor($request->getPathInfo());
        if ($factory === null) {
            return;
        }

        $limit = $factory->create($request->getClientIp() ?? 'inconnue')->consume();
        $request->attributes->set(self::ATTRIBUTE, $limit);

        if (!$limit->isAccepted()) {
            $event->setResponse($this->tooMany($limit));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $limit = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if ($limit instanceof RateLimit) {
            $this->addHeaders($event->getResponse(), $limit);
        }
    }

    private function factoryFor(string $path): ?RateLimiterFactoryInterface
    {
        return match (true) {
            str_starts_with($path, '/api/catalogue') => $this->catalogue,
            $path === '/api/games/search' => $this->search,
            $path === '/api/games' || str_starts_with($path, '/api/games/') || $path === '/api/theme-mappings' => $this->publicApi,
            default => null,
        };
    }

    private function tooMany(RateLimit $limit): JsonResponse
    {
        $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());
        $response = new JsonResponse(['error' => 'Trop de requêtes. Réessayez dans '.$wait.' s.'], 429, ['Retry-After' => (string) $wait]);
        $this->addHeaders($response, $limit);

        return $response;
    }

    private function addHeaders(\Symfony\Component\HttpFoundation\Response $response, RateLimit $limit): void
    {
        $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
        $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
    }
}
