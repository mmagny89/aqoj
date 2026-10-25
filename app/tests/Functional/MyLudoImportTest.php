<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class MyLudoImportTest extends ApiTestCase
{
    private const HEADER = "'ID;Titre;Sous-titre;Édition;Type;Note personnelle\n";

    public function testLeRapprochementDistingueReconnuAmbiguEtInconnu(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'rapproche@example.test');
        $this->jeu('Zzml Unique', 'Zzml Unique Original', 2021);
        $this->jeu('Zzml Double', null, 2012, votes: 100);
        $this->jeu('Zzml Double', null, 2019, votes: 900);
        $this->jeu('Zzml Triple', null, 2014);
        $this->jeu('Zzml Triple', null, 2017);
        $this->jeu('Zzml Futur', null, 2030);
        $this->jeu('Zzml Extension', null, 2018, extension: true);
        $this->jeu('Zzml Sous-titre', null, 2015);
        $this->jeu('Zzml Sous-titre Les Jardins', null, 2019);

        $csv = self::HEADER
            . "1;Zzml Unique;;2022;basegame;0\n"          // un seul candidat : reconnu malgré l'écart d'année
            . "2;Zzml Double;;2019;basegame;0\n"          // deux candidats, un seul de l'année d'édition : reconnu
            . "3;Zzml Triple;;2019;basegame;0\n"          // deux candidats, aucun de l'année : à trancher
            . "4;Zzml Futur;;2020;basegame;0\n"           // paru après l'édition : impossible
            . "5;Zzml Inconnu;;2020;basegame;0\n"
            . "6;Zzml Extension;;2018;basegame;0\n"       // seule une extension porte ce nom
            . "7;Zzml Extension;;2018;expansion;0\n"
            . "8;Zzml Sous-titre;Les Jardins;2019;basegame;0\n"; // le titre complet l'emporte

        $analyse = $this->call($client, 'POST', '/api/myludo/analyze', ['csv' => $csv], $token);

        self::assertResponseIsSuccessful();
        $par = fn(int $i) => $analyse['rows'][$i];
        self::assertSame('matched', $par(0)['status']);
        self::assertSame('Zzml Unique', $par(0)['game']['name']);
        self::assertSame('matched', $par(1)['status']);
        self::assertSame(2019, $par(1)['game']['yearPublished']);
        self::assertSame('ambiguous', $par(2)['status']);
        self::assertSame([2017, 2014], array_column($par(2)['candidates'], 'yearPublished'));
        self::assertSame('unmatched', $par(3)['status']);
        self::assertSame('unmatched', $par(4)['status']);
        self::assertSame('unmatched', $par(5)['status']);
        self::assertSame('matched', $par(6)['status']);
        self::assertTrue($par(6)['game']['isExpansion']);
        self::assertSame('matched', $par(7)['status']);
        self::assertSame('Zzml Sous-titre Les Jardins', $par(7)['game']['name']);
        self::assertSame(['total' => 8, 'matched' => 4, 'ambiguous' => 1, 'unmatched' => 3], $analyse['summary']);
    }

    public function testLAnalyseNeTouchePasALaLudotheque(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'analyse@example.test');
        $this->jeu('Zzml Lecture', null, 2020);

        $this->call($client, 'POST', '/api/myludo/analyze', ['csv' => self::HEADER . "1;Zzml Lecture;;2020;basegame;0\n"], $token);

        self::assertSame(0, $this->possedes('analyse@example.test'));
    }

    public function testLImportAjouteLesJeuxConfirmesEtGardeLesNotesExistantes(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'importe@example.test');
        $a = $this->jeu('Zzml A', null, 2020);
        $b = $this->jeu('Zzml B', null, 2020);
        $c = $this->jeu('Zzml C', null, 2020);
        $this->posseder('importe@example.test', $b, 9.0);

        $bilan = $this->call($client, 'POST', '/api/myludo/import', ['items' => [
            ['gameId' => $a, 'rating' => 7.5],
            ['gameId' => $b, 'rating' => null],
            ['gameId' => $c, 'rating' => 0],
        ]], $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['total' => 3, 'added' => 2, 'existing' => 1, 'rated' => 1], $bilan);
        self::assertSame(3, $this->possedes('importe@example.test'));
        self::assertSame(7.5, $this->note('importe@example.test', $a));
        self::assertSame(9.0, $this->note('importe@example.test', $b));
        self::assertNull($this->note('importe@example.test', $c));

        // Un second passage ne duplique rien.
        $this->call($client, 'POST', '/api/myludo/import', ['items' => [['gameId' => $a]]], $token);
        self::assertSame(3, $this->possedes('importe@example.test'));
    }

    public function testLImportRefuseLesDemandesInvalides(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'refuse@example.test');
        $a = $this->jeu('Zzml Refus', null, 2020);

        foreach ([[], ['items' => []], ['items' => 'x'], ['items' => [['gameId' => 'a']]], ['items' => [['rating' => 5]]]] as $corps) {
            $this->call($client, 'POST', '/api/myludo/import', $corps, $token);
            self::assertResponseStatusCodeSame(400);
        }

        $this->call($client, 'POST', '/api/myludo/import', ['items' => [['gameId' => $a], ['gameId' => 999999999]]], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->possedes('refuse@example.test'));
    }

    public function testLAnalyseRefuseUnFichierInvalideEtLAnonyme(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'invalide-ml@example.test');

        $this->call($client, 'POST', '/api/myludo/analyze', ['csv' => "nom;annee\nLama;2019\n"], $token);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'POST', '/api/myludo/analyze', ['csv' => ''], $token);
        self::assertResponseStatusCodeSame(400);

        $this->call($client, 'POST', '/api/myludo/analyze', ['csv' => self::HEADER . "1;Lama;;2019;basegame;0\n"]);
        self::assertResponseStatusCodeSame(401);
        $this->call($client, 'POST', '/api/myludo/import', ['items' => [['gameId' => 1]]]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLaRechercheMetLeTitreExactEnTeteMemeMoinsNote(): void
    {
        $client = static::createClient();
        $this->jeu('Zzrech Lama Trek', null, 2019, votes: 90000);
        $this->jeu('Zzrech Lama', null, 2019, votes: 10);
        $this->jeu('Un Zzrech Lama Autre', null, 2019, votes: 80000);
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement("UPDATE game SET rating_bgg = 9 WHERE name = 'Zzrech Lama Trek'");

        $trouves = $this->call($client, 'GET', '/api/games/search?q=Zzrech Lama&local=1');

        self::assertSame('Zzrech Lama', $trouves[0]['name']);
        self::assertSame('Zzrech Lama Trek', $trouves[1]['name']);
        self::assertSame('Un Zzrech Lama Autre', $trouves[2]['name']);
    }

    private function jeu(string $nom, ?string $original, int $annee, int $votes = 500, bool $extension = false): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, name_original, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, users_rated, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, ?, 2, 4, 30, 2, '[]', '[]', ?, ?, ?, '[]', NOW())",
            ['zzml' . md5($nom . $annee . ($extension ? 'e' : 'b')), $nom, $original, $annee, $votes, $extension ? 't' : 'f'],
        );

        return (int) $conn->lastInsertId();
    }

    private function posseder(string $email, int $jeu, ?float $note): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES ((SELECT id FROM app_user WHERE email = ?), ?, ?)',
            [$email, $jeu, $note],
        );
    }

    private function possedes(string $email): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM user_game WHERE user_id = (SELECT id FROM app_user WHERE email = ?)',
            [$email],
        );
    }

    private function note(string $email, int $jeu): ?float
    {
        $note = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT bgg_user_rating FROM user_game WHERE game_id = ? AND user_id = (SELECT id FROM app_user WHERE email = ?)',
            [$jeu, $email],
        );

        return $note === false || $note === null ? null : (float) $note;
    }
}
