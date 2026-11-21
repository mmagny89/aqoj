<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class BggUnlinkTest extends ApiTestCase
{
    private function prepare(string $email): array
    {
        $client = static::createClient();
        $token = $this->register($client, $email);
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $userId = (int) $db->fetchOne('SELECT id FROM app_user WHERE email = ?', [$email]);
        $db->executeStatement('UPDATE app_user SET bgg_username = ? WHERE id = ?', ['Kiki', $userId]);
        $db->executeStatement("INSERT INTO game (bgg_id, name, categories, mechanics, created_at) VALUES ('990001', 'Jeu test', '[]', '[]', NOW()) ON CONFLICT DO NOTHING");
        $gameId = (int) $db->fetchOne("SELECT id FROM game WHERE bgg_id = '990001'");
        $db->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES (?, ?)', [$userId, $gameId]);

        return [$client, $token, $db, $userId];
    }

    public function testDesynchroniserGardeLaLudotheque(): void
    {
        [$client, $token, $db, $userId] = $this->prepare('desync-garde@example.test');

        $res = $this->call($client, 'DELETE', '/api/bgg/link', ['clearLibrary' => false], $token);

        self::assertResponseIsSuccessful();
        self::assertTrue($res['unlinked']);
        self::assertNull($db->fetchOne('SELECT bgg_username FROM app_user WHERE id = ?', [$userId]) ?: null);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM user_game WHERE user_id = ?', [$userId]));
    }

    public function testDesynchroniserPeutViderLaLudotheque(): void
    {
        [$client, $token, $db, $userId] = $this->prepare('desync-vide@example.test');

        $res = $this->call($client, 'DELETE', '/api/bgg/link', ['clearLibrary' => true], $token);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $res['removed']);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM user_game WHERE user_id = ?', [$userId]));
    }

    public function testExigeUneAuthentification(): void
    {
        $client = static::createClient();
        $this->call($client, 'DELETE', '/api/bgg/link');
        self::assertResponseStatusCodeSame(401);
    }
}
