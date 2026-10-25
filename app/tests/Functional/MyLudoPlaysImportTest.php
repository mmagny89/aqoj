<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class MyLudoPlaysImportTest extends ApiTestCase
{
    private const HEADER = "\xEF\xBB\xBF'ID;Titre;Sous-titre;Édition;Type;EAN;Date;Lieu;Durée;\"NB Joueurs\";Joueurs;Vainqueurs;Incomplète;Solo;Équipes;Coop;\"Coop Victoire\";\"Coop Score\";Exclue;\"En ligne\";Commentaire;\"Note moyenne\";\"Note ajustée\";\"Note personnelle\"\n";

    private function csv(): string
    {
        return self::HEADER
            . "501;\"Zzplay Alpha\";;2024;basegame;;2026-10-25;Maison;25;2;\"Moi (39), Mymy (41)\";Mymy;Non;Non;Non;Non;Non;0;Non;Non;Bien joué;7,1;7,3;9\n"
            . "501;\"Zzplay Alpha\";;2024;basegame;;2026-10-24;Travail;20;3;\"Moi (5), A (6), B (7)\";B;Non;Non;Non;Non;Non;0;Non;Non;;7,1;7,3;9\n"
            . "501;\"Zzplay Alpha\";;2024;basegame;;2026-10-23;Maison;20;2;\"Moi (5), A (6)\";A;Non;Non;Non;Non;Non;0;Oui;Non;;7,1;7,3;9\n"
            . "502;\"Zzplay Introuvable\";;2020;basegame;;2026-10-22;;20;2;\"Moi (1), A (2)\";A;Non;Non;Non;Non;Non;0;Non;Non;;6;6;0\n";
    }

    public function testAnalyseApuisImportSontRejouablesSansDoublon(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'parties@example.test');
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, year_published, mechanic_families, created_at)
             VALUES ('zzplay-alpha', 'Zzplay Alpha', 2, 4, 30, 2.0, '[]', '[]', 2024, '[]', NOW())"
        );
        $gameId = (int) $conn->fetchOne("SELECT id FROM game WHERE bgg_id = 'zzplay-alpha'");

        $analyse = $this->call($client, 'POST', '/api/myludo/plays/analyze', ['csv' => $this->csv()], $token);

        self::assertResponseIsSuccessful();
        // Une partie « exclue » ne compte pas ; les jeux sont rapprochés, pas les parties.
        self::assertSame(3, $analyse['summary']['plays']);
        self::assertSame(1, $analyse['summary']['excluded']);
        self::assertSame(2, $analyse['summary']['total']);
        self::assertSame(1, $analyse['summary']['matched']);
        self::assertSame(1, $analyse['summary']['unmatched']);
        $alpha = array_values(array_filter($analyse['rows'], fn(array $r) => $r['id'] === '501'))[0];
        self::assertSame(2, $alpha['plays']);
        self::assertSame($gameId, $alpha['game']['id']);

        $bilan = $this->call($client, 'POST', '/api/myludo/plays/import', ['csv' => $this->csv(), 'mapping' => ['501' => $gameId]], $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['imported' => 2, 'duplicates' => 0, 'left' => 1, 'excluded' => 1, 'invalid' => 0], $bilan);
        $sessions = $this->call($client, 'GET', '/api/sessions', token: $token);
        self::assertCount(2, $sessions);
        $derniere = array_values($sessions)[0];
        self::assertSame($gameId, $derniere['game']['id']);
        self::assertSame(2, $derniere['playersCount']);
        self::assertSame(5, $derniere['rating']);
        self::assertSame('Bien joué', $derniere['note']);
        self::assertSame('Maison', $derniere['context']);
        self::assertStringStartsWith('2026-10-25', $derniere['playedAt']);

        // Pagination et bilan : indépendants de la page.
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?limit=1', token: $token));
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?limit=1&offset=1', token: $token));
        self::assertSame(['plays' => 2, 'games' => 1, 'liked' => 2], $this->call($client, 'GET', '/api/sessions/summary', token: $token));

        // Le même fichier rejoué n'ajoute rien, et l'analyse le sait.
        $encore = $this->call($client, 'POST', '/api/myludo/plays/import', ['csv' => $this->csv(), 'mapping' => ['501' => $gameId]], $token);
        self::assertSame(0, $encore['imported']);
        self::assertSame(2, $encore['duplicates']);
        self::assertCount(2, $this->call($client, 'GET', '/api/sessions', token: $token));
        $analyse = $this->call($client, 'POST', '/api/myludo/plays/analyze', ['csv' => $this->csv()], $token);
        self::assertSame(2, $analyse['summary']['already']);
    }

    public function testLImportExigeUneSessionEtUnJeuDuCatalogue(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/myludo/plays/analyze', ['csv' => $this->csv()]);
        self::assertResponseStatusCodeSame(401);

        $token = $this->register($client, 'parties2@example.test');
        $this->call($client, 'POST', '/api/myludo/plays/import', ['csv' => $this->csv(), 'mapping' => ['501' => 999999999]], $token);
        self::assertResponseStatusCodeSame(422);

        $this->call($client, 'POST', '/api/myludo/plays/analyze', ['csv' => "Nom;Prix\nCatan;30\n"], $token);
        self::assertResponseStatusCodeSame(422);
    }
}
