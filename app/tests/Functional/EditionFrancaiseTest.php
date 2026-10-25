<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Service\GameEnrichmentService;
use Doctrine\ORM\EntityManagerInterface;

final class EditionFrancaiseTest extends ApiTestCase
{
    /** @return array<string, mixed> */
    private function data(?array $editionFr): array
    {
        return [
            'bggId' => 'zzed1', 'name' => 'Zzed Original', 'nameFr' => $editionFr['title'] ?? null, 'editionFr' => $editionFr,
            'bggType' => 'boardgame', 'minPlayers' => 2, 'maxPlayers' => 4, 'playingTime' => 30, 'complexity' => 2.0,
            'imageUrl' => 'https://cf.geekdo-images.com/original.jpg', 'yearPublished' => 2019,
        ];
    }

    public function testLEnrichissementRetientEditeurAnneeEtImageDeLEditionFrancaise(): void
    {
        static::bootKernel();
        $service = static::getContainer()->get(GameEnrichmentService::class);

        $game = $service->hydrate(new Game(), $this->data([
            'title' => 'Zzed Français', 'year' => 2021, 'publisher' => 'Éditions Test', 'imageUrl' => 'https://cf.geekdo-images.com/fr.jpg',
        ]));

        self::assertSame('Zzed Français', $game->getName());
        self::assertSame('Éditions Test', $game->getEditionFrPublisher());
        self::assertSame(2021, $game->getEditionFrYear());
        self::assertSame('https://cf.geekdo-images.com/fr.jpg', $game->getEditionFrImageUrl());
        // L'image d'origine reste celle de la fiche : la boîte française s'y ajoute, elle ne la remplace pas en base.
        self::assertSame('https://cf.geekdo-images.com/original.jpg', $game->getImageUrl());
        $json = $game->jsonSerialize();
        self::assertSame(['Éditions Test', 2021, 'https://cf.geekdo-images.com/fr.jpg'], [$json['editionFrPublisher'], $json['editionFrYear'], $json['editionFrImageUrl']]);
    }

    public function testUneResynchroSansEditionFrancaiseGardeCeQuiEtaitConnu(): void
    {
        static::bootKernel();
        $service = static::getContainer()->get(GameEnrichmentService::class);
        $game = $service->hydrate(new Game(), $this->data(['title' => 'Zzed Français', 'year' => 2021, 'publisher' => 'Éditions Test', 'imageUrl' => null]));

        $service->hydrate($game, $this->data(null));

        self::assertSame('Éditions Test', $game->getEditionFrPublisher());
        self::assertSame(2021, $game->getEditionFrYear());
    }

    public function testLeCataloguePublicNeSertPasLEditionFrancaise(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_ACCEPT', 'application/json');
        $service = static::getContainer()->get(GameEnrichmentService::class);
        $game = $service->hydrate(new Game(), $this->data(['title' => 'Zzed Français', 'year' => 2021, 'publisher' => 'Éditions Test', 'imageUrl' => 'https://cf.geekdo-images.com/fr.jpg']));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($game);
        $em->flush();

        $fiche = $this->call($client, 'GET', '/api/catalogue/games/zzed1');

        self::assertResponseIsSuccessful();
        self::assertSame('Zzed Français', $fiche['name']);
        foreach (['editionFrPublisher', 'editionFrYear', 'editionFrImageUrl'] as $champ) {
            self::assertArrayNotHasKey($champ, $fiche);
        }
    }
}
