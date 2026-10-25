<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DescriptionTranslationTest extends ApiTestCase
{
    private const EN = "Build a palace.\n\nScore points with your tiles.";

    /** @param list<MockResponse>|callable $responses */
    private function mockTranslator(KernelBrowser $client, array|callable $responses): MockHttpClient
    {
        // Le conteneur de test doit survivre aux requêtes : sans cela, il redémarre et perd le faux client.
        $client->disableReboot();
        $http = new MockHttpClient($responses);
        static::getContainer()->set('test.translate_http_client', $http);

        return $http;
    }

    private function ok(string ...$texts): MockResponse
    {
        return new MockResponse(json_encode(['translatedText' => array_values($texts)]));
    }

    public function testUnMembreQuiOuvreLaFicheDeclencheLaTraductionEtLaGarde(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'traduit@example.test');
        $id = $this->jeu('Zztr Palais', self::EN);
        $sent = [];
        $http = $this->mockTranslator($client, function (string $m, string $u, array $o) use (&$sent): MockResponse {
            $sent[] = json_decode($o['body'], true)['q'];

            return $this->ok('Construisez un palais.', 'Marquez des points avec vos tuiles.');
        });

        $fiche = $this->call($client, 'GET', "/api/games/$id", token: $token);

        self::assertResponseIsSuccessful();
        // Les paragraphes partent séparément et les sauts de ligne d'origine reviennent.
        self::assertSame([['Build a palace.', 'Score points with your tiles.']], $sent);
        self::assertSame("Construisez un palais.\n\nMarquez des points avec vos tuiles.", $fiche['descriptionFr']);
        self::assertFalse($fiche['descriptionFrPending']);
        self::assertSame(self::EN, $fiche['description']);

        // Deuxième consultation : la traduction est en base, le service n'est plus appelé.
        $encore = $this->call($client, 'GET', "/api/games/$id", token: $token);
        self::assertSame($fiche['descriptionFr'], $encore['descriptionFr']);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testUnVisiteurAnonymeNeDeclenchePasLaTraduction(): void
    {
        $client = static::createClient();
        $id = $this->jeu('Zztr Anonyme', self::EN);
        $http = $this->mockTranslator($client, []);

        $fiche = $this->call($client, 'GET', "/api/games/$id");

        self::assertResponseIsSuccessful();
        self::assertSame(0, $http->getRequestsCount());
        self::assertNull($fiche['descriptionFr']);
        self::assertTrue($fiche['descriptionFrPending']);
    }

    public function testUnServiceEnPanneLaisseLAnglaisEtNeEstRappeleQuUneFoisParJour(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'panne@example.test');
        $id = $this->jeu('Zztr Panne', self::EN);
        $http = $this->mockTranslator($client, [new MockResponse('', ['http_code' => 503])]);

        $fiche = $this->call($client, 'GET', "/api/games/$id", token: $token);

        self::assertResponseIsSuccessful();
        self::assertNull($fiche['descriptionFr']);
        self::assertTrue($fiche['descriptionFrPending']);
        self::assertSame(self::EN, $fiche['description']);

        $this->call($client, 'GET', "/api/games/$id", token: $token);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testUneDescriptionDejaFrancaiseNEstPasEnvoyeeAuService(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'francais@example.test');
        $fr = "Dans ce jeu pour toute la famille, les joueurs sont invités à construire une ville avec des cartes. Le jeu est simple et les parties sont rapides pour les enfants.";
        $id = $this->jeu('Zztr Français', $fr);
        $http = $this->mockTranslator($client, []);

        $fiche = $this->call($client, 'GET', "/api/games/$id", token: $token);

        self::assertSame(0, $http->getRequestsCount());
        self::assertSame($fr, $fiche['descriptionFr']);
    }

    public function testUneDescriptionChangeeParBggPerdSaTraduction(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'change@example.test');
        $id = $this->jeu('Zztr Change', self::EN);
        $this->mockTranslator($client, [$this->ok('Construisez un palais.', 'Marquez des points avec vos tuiles.')]);
        $this->call($client, 'GET', "/api/games/$id", token: $token);

        // BGG change la description au passage du resync ; l'essai du jour est déjà consommé.
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE game SET description = 'A brand new text.' WHERE id = ?", [$id]);
        $fiche = $this->call($client, 'GET', "/api/games/$id", token: $token);

        self::assertNull($fiche['descriptionFr']);
        self::assertTrue($fiche['descriptionFrPending']);
    }

    public function testLeCataloguePublicNePublieJamaisLaTraduction(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'catalogue-tr@example.test');
        $id = $this->jeu('Zztr Catalogue', self::EN);
        $this->mockTranslator($client, [$this->ok('Construisez un palais.', 'Marquez des points avec vos tuiles.')]);
        $this->call($client, 'GET', "/api/games/$id", token: $token);
        $client->setServerParameter('HTTP_ACCEPT', 'application/json');

        $fiche = $this->call($client, 'GET', '/api/catalogue/games/' . 'zztr' . md5('Zztr Catalogue'));

        self::assertResponseIsSuccessful();
        foreach (['descriptionFr', 'descriptionFrHash', 'descriptionFrAt', 'descriptionFrAttemptAt'] as $champ) {
            self::assertArrayNotHasKey($champ, $fiche);
        }
    }

    private function jeu(string $nom, string $description, ?int $rang = null): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, description, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, bgg_rank, mechanic_families, source, created_at)
             VALUES (?, ?, ?, 2, 4, 30, 2, '[]', '[\"Dice Rolling\"]', 2020, ?, '[]', 'bgg_api', NOW())",
            ['zztr' . md5($nom), $nom, $description, $rang],
        );

        return (int) $conn->lastInsertId();
    }
}
