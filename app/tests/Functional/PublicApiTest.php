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
        $token = $this->register($client, 'membre-recherche@example.test');

        $result = $this->call($client, 'GET', '/api/games/search?q=zzzxqjw-introuvable', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame([], $result);
    }

    public function testLaRechercheLocaleTrouveUnJeuDeLaBaseSansAppelerBgg(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'membre-local@example.test');
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->persist((new \App\Entity\Game())->setBggId('990301')->setName('Zzlocal Unique'));
        $em->flush();

        $trouves = $this->call($client, 'GET', '/api/games/search?q=zzlocal&local=1', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['Zzlocal Unique'], array_column($trouves, 'name'));
        self::assertSame([], $this->call($client, 'GET', '/api/games/search?q=zzintrouvable&local=1', token: $token));
    }

    public function testUneFicheInconnueDonne404(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'membre-fiche@example.test');

        $this->call($client, 'GET', '/api/games/999999999', token: $token);

        self::assertResponseStatusCodeSame(404);
    }

    /** @return iterable<string, array{string}> */
    public static function routesReserveesAuxMembres(): iterable
    {
        yield 'recherche' => ['/api/games/search?q=azul'];
        yield 'fiche' => ['/api/games/1'];
        yield 'jeux similaires' => ['/api/games/1/similar'];
        yield 'accueil' => ['/api/games/home-reco'];
        yield 'liste' => ['/api/games'];
        yield 'ludothèque' => ['/api/library'];
        yield 'thèmes' => ['/api/theme-mappings'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routesReserveesAuxMembres')]
    public function testLeCatalogueEstFermeAuxVisiteursAnonymes(string $uri): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', $uri);

        self::assertResponseStatusCodeSame(401);
    }
}
