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
        yield 'journal des tâches' => ['GET', '/api/admin/jobs'];
        yield 'explorateur (compteurs)' => ['GET', '/api/admin/games/filters'];
        yield 'explorateur (liste)' => ['GET', '/api/admin/games'];
        yield 'explorateur (fiche)' => ['GET', '/api/admin/games/13'];
        yield 'invitations (liste)' => ['GET', '/api/admin/invitations'];
        yield 'invitations (création)' => ['POST', '/api/admin/invitations'];
        yield 'invitations (révocation)' => ['POST', '/api/admin/invitations/1/revoke'];
        yield 'tâches (arrêt demandé)' => ['POST', '/api/admin/jobs/1/stop'];
        yield 'tâches (arrêt forcé)' => ['POST', '/api/admin/jobs/1/kill'];
        yield 'ludothèque asso (liste)' => ['GET', '/api/admin/association/games'];
        yield 'ludothèque asso (retrait)' => ['DELETE', '/api/admin/association/games/1'];
        yield 'comptes (liste)' => ['GET', '/api/admin/users'];
        yield 'comptes (rôle)' => ['PATCH', '/api/admin/users/1'];
    }

    #[DataProvider('routesAdmin')]
    public function testUneRouteAdminRefuseLAnonyme(string $method, string $uri): void
    {
        $client = static::createClient();

        $this->call($client, $method, $uri);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('routesAdmin')]
    public function testUneRouteAdminRefuseUnJoueurOrdinaire(string $method, string $uri): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'ordinaire@example.test');

        $body = $this->call($client, $method, $uri, token: $token);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Accès refusé.', $body['error']);
    }

    public function testLesSessionsDeJeuExigentUneAuthentification(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/sessions');

        self::assertResponseStatusCodeSame(401);
    }
}
