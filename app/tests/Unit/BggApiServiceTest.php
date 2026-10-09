<?php

namespace App\Tests\Unit;

use App\Service\BggApiException;
use App\Service\BggApiService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BggApiServiceTest extends TestCase
{
    private const THING = <<<'XML'
        <items>
          <item type="boardgame" id="13">
            <name type="primary" value="Catan"/>
            <name type="alternate" value="Les Colons de Catane"/>
            <description>Un jeu &amp;amp; ses colons.</description>
            <minplayers value="3"/><maxplayers value="4"/><playingtime value="90"/><minage value="10"/>
            <yearpublished value="1995"/>
            <link type="boardgamemechanic" id="1" value="Dice Rolling"/>
            <link type="boardgamecategory" id="2" value="Negotiation"/>
            <link type="boardgameexpansion" id="926" value="Catan: Seafarers"/>
            <statistics><ratings>
              <usersrated value="120000"/><average value="7.1"/><averageweight value="2.3"/>
              <ranks><rank name="boardgame" value="500"/></ranks>
            </ratings></statistics>
            <versions>
              <item><yearpublished value="2010"/><canonicalname value="Catane (2010)"/>
                <link type="language" id="2184" value="French"/></item>
              <item><yearpublished value="2015"/><canonicalname value="Catane  (2015) l\'édition"/>
                <link type="language" id="2184" value="French"/></item>
              <item><yearpublished value="2020"/><canonicalname value="Catan Multi"/>
                <link type="language" id="2184" value="French"/><link type="language" id="2000" value="English"/></item>
            </versions>
          </item>
        </items>
        XML;

    private function service(MockResponse ...$responses): BggApiService
    {
        return new BggApiService(new MockHttpClient($responses), '');
    }

    public function testLeTitreFrancaisVientDeLEditionMonolingueLaPlusRecente(): void
    {
        $games = $this->service(new MockResponse(self::THING))->fetchGamesDetails(['13']);

        self::assertCount(1, $games);
        // 2015 gagne sur 2010 ; l'édition multilingue de 2020 est écartée ; apostrophe échappée et espaces normalisés.
        self::assertSame("Catane (2015) l'édition", $games[0]['nameFr']);
        self::assertSame('Catan', $games[0]['name']);
    }

    public function testSansEditionFrancaiseLeTitreFrancaisEstNul(): void
    {
        $xml = '<items><item type="boardgame" id="1"><name type="primary" value="Azul"/>'
            . '<name type="alternate" value="Azul (édition française)"/></item></items>';

        $games = $this->service(new MockResponse($xml))->fetchGamesDetails(['1']);

        self::assertNull($games[0]['nameFr']);
    }

    public function testLesDonneesDeFicheSontLues(): void
    {
        $game = $this->service(new MockResponse(self::THING))->fetchGamesDetails(['13'])[0];

        self::assertSame(3, $game['minPlayers']);
        self::assertSame(4, $game['maxPlayers']);
        self::assertSame(2.3, $game['complexity']);
        self::assertSame(500, $game['bggRank']);
        self::assertSame(['Dice Rolling'], $game['mechanics']);
        self::assertSame(['926'], $game['expansionIds']);
    }

    public function testPlusDeVingtIdentifiantsSontRefuses(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->fetchGamesDetails(array_map('strval', range(1, 21)));
    }

    public function testUnStatutDefinitifLeveUneExceptionPortantLeCode(): void
    {
        try {
            $this->service(new MockResponse('', ['http_code' => 400]))->fetchGamesDetails(['1']);
            self::fail('Une BggApiException était attendue.');
        } catch (BggApiException $e) {
            self::assertSame(400, $e->getCode());
        }
    }

    public function testUneCollectionPriveeDonneUnMessageClair(): void
    {
        try {
            $this->service(new MockResponse('', ['http_code' => 401]))->fetchCollection('inconnu');
            self::fail('Une BggApiException était attendue.');
        } catch (BggApiException $e) {
            self::assertSame(401, $e->getCode());
            self::assertStringContainsString('publique', $e->getMessage());
        }
    }

    public function testLaCollectionIndiqueLesExtensionsEtLesNotes(): void
    {
        $xml = '<items>'
            . '<item objectid="13" subtype="boardgame"><stats><rating value="8"/></stats></item>'
            . '<item objectid="926" subtype="boardgameexpansion"><stats><rating value="N/A"/></stats></item>'
            . '</items>';

        $items = $this->service(new MockResponse($xml))->fetchCollection('joueur', true);

        self::assertSame(
            [
                ['bggId' => '13', 'userRating' => 8.0, 'isExpansion' => false],
                ['bggId' => '926', 'userRating' => null, 'isExpansion' => true],
            ],
            $items,
        );
    }
}
