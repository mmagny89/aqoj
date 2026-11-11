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

    public function testLaRechercheLocaleTrouveUnJeuDeLaBaseSansAppelerBgg(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->persist((new \App\Entity\Game())->setBggId('990301')->setName('Zzlocal Unique'));
        $em->flush();

        $trouves = $this->call($client, 'GET', '/api/games/search?q=zzlocal&local=1');

        self::assertResponseIsSuccessful();
        self::assertSame(['Zzlocal Unique'], array_column($trouves, 'name'));
        self::assertSame([], $this->call($client, 'GET', '/api/games/search?q=zzintrouvable&local=1'));
    }

    public function testUneFicheInconnueDonne404(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/games/999999999');

        self::assertResponseStatusCodeSame(404);
    }
}
