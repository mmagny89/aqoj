<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class SessionFiltersStatsTest extends ApiTestCase
{
    public function testFiltresSourceEtStatistiquesParJeu(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'filtres-moi@example.test');
        $autre = $this->register($client, 'filtres-autre@example.test');
        $conn = static::getContainer()->get(Connection::class);
        foreach (['Zzazul' => 'zzazul', 'Zzcatan' => 'zzcatan'] as $name => $bggId) {
            $conn->executeStatement(
                "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, mechanic_families, created_at)
                 VALUES (?, ?, 2, 4, 30, 2.0, '[]', '[]', '[]', NOW())",
                [$bggId, $name],
            );
        }
        $azul = (int) $conn->fetchOne("SELECT id FROM game WHERE bgg_id = 'zzazul'");
        $catan = (int) $conn->fetchOne("SELECT id FROM game WHERE bgg_id = 'zzcatan'");

        $this->call($client, 'POST', '/api/sessions', ['gameId' => $azul, 'playersCount' => 2, 'rating' => 5, 'playedAt' => '2025-03-01T12:00:00+00:00'], $moi);
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $azul, 'playersCount' => 4, 'rating' => 1, 'playedAt' => '2026-03-01T12:00:00+00:00', 'note' => 'Soirée été'], $moi);
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $catan, 'playersCount' => 4, 'playedAt' => '2026-04-01T12:00:00+00:00'], $moi);
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $catan, 'playersCount' => 3], $autre);
        // Une partie importée de MyLudo : même table, empreinte d'import renseignée.
        $conn->executeStatement(
            "INSERT INTO game_session (user_id, game_id, players_count, played_at, import_key)
             VALUES ((SELECT id FROM app_user WHERE email = 'filtres-moi@example.test'), ?, 5, '2026-05-01 12:00:00', 'abcdef0123456789abcdef0123456789')",
            [$catan],
        );

        $all = $this->call($client, 'GET', '/api/sessions', token: $moi);
        self::assertCount(4, $all);
        self::assertSame(['myludo'], array_values(array_unique(array_column(array_filter($all, fn($s) => $s['playersCount'] === 5), 'source'))));
        self::assertContains('manual', array_column($all, 'source'));

        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?source=myludo', token: $moi));
        self::assertCount(3, $this->call($client, 'GET', '/api/sessions?source=manual', token: $moi));
        self::assertCount(2, $this->call($client, 'GET', '/api/sessions?q=azul', token: $moi));
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?q=ete', token: $moi), 'recherche sans accents, dans le commentaire');
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?rating=liked', token: $moi));
        self::assertCount(2, $this->call($client, 'GET', '/api/sessions?players=4', token: $moi));
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?year=2025', token: $moi));
        self::assertCount(1, $this->call($client, 'GET', '/api/sessions?q=catan&players=4&year=2026', token: $moi));

        $summary = $this->call($client, 'GET', '/api/sessions/summary?q=azul', token: $moi);
        self::assertSame(2, $summary['plays']);
        self::assertSame(1, $summary['games']);
        self::assertSame(2025, $summary['firstYear'], 'les années proposées ignorent les filtres');

        $stats = $this->call($client, 'GET', '/api/sessions/stats', token: $moi);
        self::assertSame(['Zzazul', 'Zzcatan'], array_column($stats['games'], 'name'), 'à égalité, par nom');
        self::assertSame(2, $stats['games'][0]['plays']);
        self::assertEquals(3.0, $stats['games'][0]['averagePlayers']);
        self::assertSame([['players' => 2, 'plays' => 1], ['players' => 4, 'plays' => 1]], $stats['games'][0]['byPlayers']);
        self::assertEquals(4.5, $stats['games'][1]['averagePlayers']);
        self::assertSame([['year' => 2026, 'plays' => 3], ['year' => 2025, 'plays' => 1]], $stats['byYear']);
        self::assertEquals(3.8, $stats['averagePlayers']);

        $this->call($client, 'GET', '/api/sessions/stats');
        self::assertResponseStatusCodeSame(401);
    }
}
