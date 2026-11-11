<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class StylesDeJeuMyLudoTest extends ApiTestCase
{
    /** Une collection MyLudo n'a presque jamais de notes : ses styles viennent de ce qu'on possède. */
    public function testUneCollectionSansNoteDonneSesStylesEtSesThemes(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'myludo-styles@example.test');
        $conn = static::getContainer()->get(Connection::class);

        foreach ([
            ['Zzstyle Ouvriers 1', ['worker_placement'], ['Economic']],
            ['Zzstyle Ouvriers 2', ['worker_placement'], ['Economic', 'Farming', 'Expansion for Base-game']],
            ['Zzstyle Encheres', ['auction'], ['Economic']],
        ] as [$nom, $familles, $categories]) {
            $this->jeu($conn, $nom, $familles, $categories);
            $conn->executeStatement(
                'INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?), NULL)',
                ['myludo-styles@example.test', $nom],
            );
        }
        // Un jeu importé mais pas encore enrichi : compté, mais pas analysé.
        $conn->executeStatement("INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, mechanic_families, created_at) VALUES ('zzstyle-vide', 'Zzstyle Vide', 2, 4, 30, 0, '[]', '[]', '[]', NOW())");
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?))', ['myludo-styles@example.test', 'Zzstyle Vide']);

        $prefs = $this->call($client, 'POST', '/api/user/preferences/recompute', token: $token);

        self::assertSame(['worker_placement', 'auction'], $prefs['topEngines']);
        self::assertSame('Economic', $prefs['topCategories'][0]);
        self::assertContains('Farming', $prefs['topCategories']);
        self::assertNotContains('Expansion for Base-game', $prefs['topCategories']);
        self::assertSame(['total' => 4, 'analysed' => 3], $prefs['coverage']);
        self::assertSame(['total' => 4, 'analysed' => 3], $this->call($client, 'GET', '/api/user/preferences', token: $token)['coverage']);
    }

    public function testQuelquesJeuxNotesNeNoientPasUneCollectionSansNote(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'mixte-styles@example.test');
        $conn = static::getContainer()->get(Connection::class);

        // Un seul jeu noté 7 (4 points + 1) contre six jeux sans note (6 points) : la note ne noie pas la collection.
        $this->jeu($conn, 'Zzmixte Note', ['auction'], []);
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?), 7)', ['mixte-styles@example.test', 'Zzmixte Note']);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $lettre) {
            $this->jeu($conn, 'Zzmixte ' . $lettre, ['worker_placement'], []);
            $conn->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?))', ['mixte-styles@example.test', 'Zzmixte ' . $lettre]);
        }

        $prefs = $this->call($client, 'POST', '/api/user/preferences/recompute', token: $token);

        self::assertSame(['worker_placement', 'auction'], $prefs['topEngines']);
    }

    private function jeu(Connection $conn, string $nom, array $familles, array $categories): void
    {
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, mechanic_families, created_at)
             VALUES (?, ?, 2, 4, 45, 2.0, ?, '[\"Dice Rolling\"]', ?, NOW())",
            ['zz' . md5($nom), $nom, json_encode($categories), json_encode($familles)],
        );
    }
}
