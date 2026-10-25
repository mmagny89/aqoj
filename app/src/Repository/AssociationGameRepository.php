<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/**
 * Ludothèque de l'association : les jeux que les administrateurs ont renseignés (import MyLudo ou BGG).
 * Table `association_game`, hors mapping Doctrine comme `user_game`.
 */
class AssociationGameRepository
{
    public function __construct(private readonly Connection $conn) {}

    /**
     * @param list<int> $gameIds
     *
     * @return array{added: int, existing: int}
     */
    public function addMany(array $gameIds, User $by): array
    {
        $gameIds = array_values(array_unique($gameIds));
        if ($gameIds === []) {
            return ['added' => 0, 'existing' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
        $existing = (int) $this->conn->fetchOne("SELECT COUNT(*) FROM association_game WHERE game_id IN ($placeholders)", $gameIds);

        foreach ($gameIds as $gameId) {
            $this->conn->executeStatement(
                'INSERT INTO association_game (game_id, added_by) VALUES (?, ?) ON CONFLICT (game_id) DO NOTHING',
                [$gameId, $by->getId()],
            );
        }

        return ['added' => count($gameIds) - $existing, 'existing' => $existing];
    }

    public function count(): int
    {
        return (int) $this->conn->fetchOne('SELECT COUNT(*) FROM association_game');
    }

    /** @return list<array<string, mixed>> */
    public function list(int $limit = 1000): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT g.id, g.name, g.year_published, g.min_players, g.max_players, g.playing_time, g.is_expansion, ag.added_at
             FROM association_game ag JOIN game g ON g.id = ag.game_id
             ORDER BY LOWER(g.name), g.id LIMIT ?',
            [$limit],
        );

        return array_map(fn(array $r) => [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'yearPublished' => $r['year_published'] !== null && (int) $r['year_published'] > 0 ? (int) $r['year_published'] : null,
            'minPlayers'    => (int) $r['min_players'],
            'maxPlayers'    => (int) $r['max_players'],
            'playingTime'   => (int) $r['playing_time'],
            'isExpansion'   => (bool) $r['is_expansion'],
            'addedAt'       => $r['added_at'],
        ], $rows);
    }

    public function remove(int $gameId): bool
    {
        return $this->conn->executeStatement('DELETE FROM association_game WHERE game_id = ?', [$gameId]) > 0;
    }
}
