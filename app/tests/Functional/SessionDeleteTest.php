<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class SessionDeleteTest extends ApiTestCase
{
    public function testOnSupprimeSesPartiesMaisPasCellesDesAutres(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'suppr-moi@example.test');
        $autre = $this->register($client, 'suppr-autre@example.test');
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, mechanic_families, created_at)
             VALUES ('zzsuppr', 'Zzsuppr', 2, 4, 30, 2.0, '[]', '[]', '[]', NOW())"
        );
        $gameId = (int) $conn->fetchOne("SELECT id FROM game WHERE bgg_id = 'zzsuppr'");
        $mienne = $this->call($client, 'POST', '/api/sessions', ['gameId' => $gameId, 'playersCount' => 3, 'rating' => 5], $moi);
        $siennes = $this->call($client, 'POST', '/api/sessions', ['gameId' => $gameId, 'playersCount' => 2], $autre);

        // Celle d'un autre : introuvable, et elle reste.
        $this->call($client, 'DELETE', '/api/sessions/' . $siennes['id'], token: $moi);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions', token: $autre));

        $this->call($client, 'DELETE', '/api/sessions/' . $mienne['id'], token: $moi);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->call($client, 'GET', '/api/sessions', token: $moi));
        self::assertSame(0, $this->call($client, 'GET', '/api/sessions/summary', token: $moi)['plays']);

        $this->call($client, 'DELETE', '/api/sessions/' . $mienne['id'], token: $moi);
        self::assertResponseStatusCodeSame(404);
        $this->call($client, 'DELETE', '/api/sessions/' . $mienne['id']);
        self::assertResponseStatusCodeSame(401);
    }
}
