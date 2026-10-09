<?php

namespace App\Tests\Functional;

final class PublicApiTest extends ApiTestCase
{
    public function testLaSanteRepond(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/health');

        self::assertResponseIsSuccessful();
    }

    public function testLaRechercheSansResultatNeCasseRienQuandBggEstIndisponible(): void
    {
        $client = static::createClient();

        $result = $this->call($client, 'GET', '/api/games/search?q=zzzxqjw-introuvable');

        self::assertResponseIsSuccessful();
        self::assertSame([], $result);
    }

    public function testUneFicheInconnueDonne404(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/games/999999999');

        self::assertResponseStatusCodeSame(404);
    }
}
