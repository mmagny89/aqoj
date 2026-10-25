<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class GameDetailExpansionsTest extends ApiTestCase
{
    public function testLesExtensionsDisentOuLesTrouverSansNommerLeProprietaire(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'ext-moi@example.test');
        $this->register($client, 'ext-voisin@example.test');
        $conn = static::getContainer()->get(Connection::class);

        $base = $this->jeu('Zzext Base', 8.0, false, ['zzext-a', 'zzext-b', 'zzext-c', 'zzext-d']);
        $perso = $this->jeu('Zzext Perso', 6.0, true, [], 'zzext-a');
        $asso = $this->jeu('Zzext Asso', 7.0, true, [], 'zzext-b');
        $voisin = $this->jeu('Zzext Voisin', 7.5, true, [], 'zzext-c');
        $rien = $this->jeu('Zzext Rien', 9.0, true, [], 'zzext-d');

        $conn->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), ?)', ['ext-moi@example.test', $perso]);
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), ?)', ['ext-voisin@example.test', $voisin]);
        $conn->executeStatement('INSERT INTO association_game (game_id) VALUES (?)', [$asso]);

        $fiche = $this->call($client, 'GET', '/api/games/' . $base, token: $moi);

        self::assertResponseIsSuccessful();
        // Les miennes d'abord, puis celles qu'on peut emprunter (mieux notées d'abord), puis le reste.
        self::assertSame(['Zzext Perso', 'Zzext Voisin', 'Zzext Asso', 'Zzext Rien'], array_column($fiche['expansions'], 'name'));
        $parNom = array_column($fiche['expansions'], null, 'name');
        self::assertTrue($parNom['Zzext Perso']['owned']);
        self::assertTrue($parNom['Zzext Asso']['inAssociation']);
        self::assertFalse($parNom['Zzext Asso']['withMember']);
        self::assertTrue($parNom['Zzext Voisin']['withMember']);
        self::assertFalse($parNom['Zzext Rien']['inAssociation'] || $parNom['Zzext Rien']['withMember']);
        // Un booléen : ni identité ni nombre de membres.
        self::assertStringNotContainsString('ext-voisin', json_encode($fiche));
        unset($rien);
    }

    private function jeu(string $nom, float $note, bool $extension, array $extensions = [], ?string $bggId = null): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, rating_bgg, users_rated, is_expansion, mechanic_families, expansion_bgg_ids, created_at)
             VALUES (?, ?, 2, 4, 45, 2.0, '[]', '[\"Dice Rolling\"]', 2020, ?, 500, ?, '[]', ?, NOW())",
            [$bggId ?? 'zz' . md5($nom), $nom, $note, $extension ? 't' : 'f', json_encode($extensions)],
        );

        return (int) $conn->fetchOne('SELECT id FROM game WHERE name = ?', [$nom]);
    }
}
