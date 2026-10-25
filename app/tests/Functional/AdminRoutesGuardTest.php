<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\Routing\RouterInterface;

/**
 * Filet contre l'oubli : TOUTE route sous /api/admin est refusée à l'anonyme (401) et au joueur ordinaire (403),
 * sans que la liste ait à être tenue à la main. Une nouvelle route d'administration est couverte d'office.
 */
final class AdminRoutesGuardTest extends ApiTestCase
{
    public function testToutesLesRoutesAdminExigentLAdministration(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'ordinaire-garde@example.test');
        $routes = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $route) {
            if (!str_starts_with($route->getPath(), '/api/admin')) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->getPath());
            foreach ($route->getMethods() ?: ['GET'] as $method) {
                $routes[] = [$method, $uri];
            }
        }

        self::assertGreaterThan(15, \count($routes), 'Les routes d\'administration ne sont plus listées.');
        foreach ($routes as [$method, $uri]) {
            $this->call($client, $method, $uri);
            self::assertResponseStatusCodeSame(401, "$method $uri devrait refuser l'anonyme.");
            $this->call($client, $method, $uri, token: $token);
            self::assertResponseStatusCodeSame(403, "$method $uri devrait refuser un joueur ordinaire.");
        }
    }
}
