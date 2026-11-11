<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class ForgottenGemsTest extends ApiTestCase
{
    public function testUnJeuNoteNEstJamaisOublie(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'oublies@example.test');
        $conn = static::getContainer()->get(Connection::class);

        $this->jeu($conn, 'Zzoubli Note', false);
        $this->jeu($conn, 'Zzoubli Ancien', false);
        $this->jeu($conn, 'Zzoubli Recent', false);
        $this->jeu($conn, 'Zzoubli Rien', false);
        $this->jeu($conn, 'Zzoubli Extension', true);
        foreach ([['Zzoubli Note', 8], ['Zzoubli Ancien', null], ['Zzoubli Recent', null], ['Zzoubli Rien', null], ['Zzoubli Extension', null]] as [$nom, $note]) {
            $conn->executeStatement(
                'INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?), ?)',
                ['oublies@example.test', $nom, $note],
            );
        }
        $this->partie($conn, 'Zzoubli Ancien', '400 days');
        $this->partie($conn, 'Zzoubli Recent', '10 days');

        $oublies = $this->call($client, 'GET', '/api/games/forgotten', token: $token);
        $raisons = [];
        foreach ($oublies as $o) {
            $raisons[$o['game']['name']] = $o['reason'];
        }

        // Une partie récente sort le jeu de la liste ; une extension n'« s'oublie » pas.
        self::assertArrayNotHasKey('Zzoubli Recent', $raisons);
        self::assertArrayNotHasKey('Zzoubli Extension', $raisons);
        self::assertStringContainsString('Pas joué depuis', $raisons['Zzoubli Ancien']);
        // Une note prouve que le jeu a été joué : il ne sort jamais, avec ou sans partie enregistrée.
        self::assertArrayNotHasKey('Zzoubli Note', $raisons);
        self::assertSame('Aucune partie enregistrée, dans votre collection', $raisons['Zzoubli Rien']);
        foreach ($raisons as $raison) {
            self::assertStringNotContainsStringIgnoringCase('jamais joué', $raison);
        }
    }

    private function jeu(Connection $conn, string $nom, bool $extension): void
    {
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, year_published, rating_bgg, users_rated, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, 2, 4, 45, 2.0, '[]', '[\"Dice Rolling\"]', 2015, 7.5, 1000, ?, '[]', NOW())",
            ['zz' . md5($nom), $nom, $extension ? 't' : 'f'],
        );
    }

    private function partie(Connection $conn, string $jeu, string $ilYA): void
    {
        $conn->executeStatement(
            "INSERT INTO game_session (game_id, players_count, played_at, user_id)
             VALUES ((SELECT id FROM game WHERE bgg_id = ?), 3, NOW() - ?::interval, (SELECT id FROM app_user WHERE email = ?))",
            ['zz' . md5($jeu), $ilYA, 'oublies@example.test'],
        );
    }
}
