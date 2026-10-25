<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ApiRateLimitTest extends ApiTestCase
{
    public function testLesLecturesDuCatalogueSontLimiteesParAdresse(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/catalogue/games?itemsPerPage=1');
        self::assertResponseIsSuccessful();
        self::assertSame('240', $client->getResponse()->headers->get('X-RateLimit-Limit'));
        self::assertSame('239', $client->getResponse()->headers->get('X-RateLimit-Remaining'));

        for ($i = 0; $i < 239; $i++) {
            $client->request('GET', '/api/catalogue/games?itemsPerPage=1');
        }
        self::assertResponseIsSuccessful();

        $reponse = $this->call($client, 'GET', '/api/catalogue/games?itemsPerPage=1');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertSame('0', $client->getResponse()->headers->get('X-RateLimit-Remaining'));
        self::assertStringContainsString('Trop de requêtes', $reponse['error']);
    }

    public function testLaRechercheEstPlusSerreeQueLeReste(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 120; $i++) {
            $client->request('GET', '/api/games/search?q=zzzxqjw-introuvable&local=1');
            self::assertResponseIsSuccessful();
        }
        $client->request('GET', '/api/games/search?q=zzzxqjw-introuvable&local=1');
        self::assertResponseStatusCodeSame(429);

        // Le catalogue a son propre compteur : la recherche épuisée ne l'affecte pas.
        $client->request('GET', '/api/catalogue/games?itemsPerPage=1');
        self::assertResponseIsSuccessful();
    }

    public function testLesRoutesDeCompteEtLaSanteNeSontPasPlafonnees(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertNull($client->getResponse()->headers->get('X-RateLimit-Limit'));
    }
}
