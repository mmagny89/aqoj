<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CatalogueApiTest extends ApiTestCase
{
    public function testLeCatalogueEstPubliqueEtFiltrable(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->creerJeu('990001', 'Zzcat Ancien', 1999, 2.0);
        $this->creerJeu('990002', 'Zzcat Recent', 2022, 3.5);

        $liste = $this->call($client, 'GET', '/api/catalogue/games?name=Zzcat&yearPublished[gte]=2020&order[yearPublished]=desc');

        self::assertResponseIsSuccessful();
        self::assertSame(['990002'], array_column($liste, 'bggId'));
        $jeux = array_values($liste);
        self::assertSame(3.5, $jeux[0]['complexity']);
        self::assertFalse($jeux[0]['isExpansion']);
    }

    public function testUneFicheSeLitParSonIdentifiantBgg(): void
    {
        $client = static::createClient();
        $this->creerJeu('990003', 'Zzcat Fiche', 2010, 2.5);

        $fiche = $this->call($client, 'GET', '/api/catalogue/games/990003');

        self::assertResponseIsSuccessful();
        self::assertSame('Zzcat Fiche', $fiche['name']);
    }

    public function testRienDePriveNeSortEtLEcritureEstRefusee(): void
    {
        $client = static::createClient();
        $this->creerJeu('990004', 'Zzcat Prive', 2010, 2.5);

        $fiche = $this->call($client, 'GET', '/api/catalogue/games/990004');
        foreach (['id', 'source', 'createdAt', 'lastFetchAttemptAt'] as $champ) {
            self::assertArrayNotHasKey($champ, $fiche);
        }

        $this->ecrire($client, 'POST');
        self::assertResponseStatusCodeSame(405);
        $this->ecrire($client, 'DELETE', '/990004');
        self::assertResponseStatusCodeSame(405);
    }

    public function testUneFicheInconnueDonne404(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/catalogue/games/999999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function ecrire(KernelBrowser $client, string $methode, string $suffixe = ''): void
    {
        $this->call($client, $methode, '/api/catalogue/games' . $suffixe, $methode === 'POST' ? ['name' => 'x'] : null);
    }

    private function creerJeu(string $bggId, string $nom, int $annee, float $poids): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $jeu = (new Game())->setBggId($bggId)->setName($nom)->setYearPublished($annee)->setComplexity($poids);
        $em->persist($jeu);
        $em->flush();
    }
}
