<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

final class AccessControlTest extends ApiTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function routesAdmin(): iterable
    {
        yield 'stats' => ['GET', '/api/admin/stats'];
        yield 'mécaniques (liste)' => ['GET', '/api/admin/mechanic-mappings'];
        yield 'mécaniques (non mappées)' => ['GET', '/api/admin/mechanic-mappings/unmapped'];
        yield 'mécaniques (recalcul)' => ['POST', '/api/admin/mechanic-mappings/recompute'];
        yield 'thèmes (création)' => ['POST', '/api/admin/theme-mappings'];
        yield 'thèmes (non mappés)' => ['GET', '/api/admin/theme-mappings/unmapped'];
    }

    #[DataProvider('routesAdmin')]
    public function testUneRouteAdminRefuseLAnonyme(string $method, string $uri): void
    {
        $client = static::createClient();

        $this->call($client, $method, $uri);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('routesAdmin')]
    public function testUneRouteAdminRefuseUnJoueurOrdinaire(string $method, string $uri): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'ordinaire@example.test');

        $this->call($client, $method, $uri, token: $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testLesSessionsDeJeuExigentUneAuthentification(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/sessions');

        self::assertResponseStatusCodeSame(401);
    }
}
