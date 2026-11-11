<?php

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    /**
     * Les parties d'un joueur, filtrées, de la plus récente à la plus ancienne.
     *
     * @param array{q?: string, source?: string, rating?: string, players?: int, year?: int} $filters
     * @return list<GameSession>
     */
    public function findByUser(User $user, int $limit = 50, int $offset = 0, array $filters = []): array
    {
        [$where, $params] = $this->where($user, $filters);
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT s.id FROM game_session s JOIN game g ON g.id = s.game_id WHERE ' . $where
            . ' ORDER BY s.played_at DESC, s.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );
        if ($ids === []) {
            return [];
        }

        $byId = [];
        foreach ($this->findBy(['id' => $ids]) as $session) {
            $byId[$session->getId()] = $session;
        }

        return array_values(array_filter(array_map(fn($id) => $byId[(int) $id] ?? null, $ids)));
    }

    /**
     * Clause WHERE commune à la liste, au bilan et aux statistiques (alias `s` pour la partie, `g` pour le jeu).
     *
     * @param array{q?: string, source?: string, rating?: string, players?: int, year?: int} $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(User $user, array $filters): array
    {
        $where = ['s.user_id = ?'];
        $params = [$user->getId()];

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(unaccent(lower(g.name)) LIKE unaccent(lower(?)) OR unaccent(lower(COALESCE(g.name_original, ''))) LIKE unaccent(lower(?)) OR unaccent(lower(COALESCE(s.note, ''))) LIKE unaccent(lower(?)) OR unaccent(lower(COALESCE(s.context, ''))) LIKE unaccent(lower(?)))";
            array_push($params, $like, $like, $like, $like);
        }
        match ($filters['source'] ?? '') {
            'myludo' => $where[] = 's.import_key IS NOT NULL',
            'manual' => $where[] = 's.import_key IS NULL',
            default  => null,
        };
        match ($filters['rating'] ?? '') {
            'liked'    => $where[] = 's.rating = 5',
            'disliked' => $where[] = 's.rating = 1',
            'none'     => $where[] = 's.rating IS NULL',
            default    => null,
        };
        if (($filters['players'] ?? 0) > 0) {
            $where[] = 's.players_count = ?';
            $params[] = $filters['players'];
        }
        if (($filters['year'] ?? 0) > 0) {
            $where[] = 'EXTRACT(YEAR FROM s.played_at) = ?';
            $params[] = $filters['year'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Retourne les stats agrégées de sessions pour tous les jeux d'un utilisateur.
     * Une seule requête SQL pour tout le catalogue — utilisé par le moteur de recommandation.
     *
     * @return array<int, array{sessionCount: int, lastPlayedAt: \DateTimeImmutable|null, bestRating: int|null, worstRating: int|null}>
     */
    public function getStatsForUser(User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $rows = $conn->fetchAllAssociative(
            'SELECT
                game_id,
                COUNT(*)                                        AS session_count,
                MAX(played_at)                                  AS last_played_at,
                MAX(rating)                                     AS best_rating,
                MIN(rating) FILTER (WHERE rating IS NOT NULL)   AS worst_rating
             FROM game_session
             WHERE user_id = ?
             GROUP BY game_id',
            [$user->getId()]
        );

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['game_id']] = [
                'sessionCount' => (int) $row['session_count'],
                'lastPlayedAt' => $row['last_played_at']
                    ? new \DateTimeImmutable($row['last_played_at'])
                    : null,
                'bestRating'  => $row['best_rating'] !== null ? (int) $row['best_rating'] : null,
                'worstRating' => $row['worst_rating'] !== null ? (int) $row['worst_rating'] : null,
            ];
        }

        return $stats;
    }

    /**
     * Le bilan des parties d'un joueur (filtrées), quelle que soit la page affichée.
     * `firstYear` ignore les filtres : il borne la liste des années proposées.
     *
     * @param array{q?: string, source?: string, rating?: string, players?: int, year?: int} $filters
     * @return array{plays: int, games: int, liked: int, myludo: int, firstYear: int|null}
     */
    public function summaryForUser(User $user, array $filters = []): array
    {
        $conn = $this->getEntityManager()->getConnection();
        [$where, $params] = $this->where($user, $filters);
        $row = $conn->fetchAssociative(
            'SELECT COUNT(*) AS plays, COUNT(DISTINCT s.game_id) AS games, COUNT(*) FILTER (WHERE s.rating = 5) AS liked,
                    COUNT(*) FILTER (WHERE s.import_key IS NOT NULL) AS myludo
             FROM game_session s JOIN game g ON g.id = s.game_id WHERE ' . $where,
            $params,
        );
        $first = $conn->fetchOne('SELECT MIN(EXTRACT(YEAR FROM played_at))::int FROM game_session WHERE user_id = ?', [$user->getId()]);

        return [
            'plays' => (int) $row['plays'], 'games' => (int) $row['games'], 'liked' => (int) $row['liked'],
            'myludo' => (int) $row['myludo'], 'firstYear' => $first === false || $first === null ? null : (int) $first,
        ];
    }

    /**
     * Statistiques des parties (filtrées) : par jeu avec la répartition des joueurs, par nombre de joueurs, par année.
     *
     * @param array{q?: string, source?: string, rating?: string, players?: int, year?: int} $filters
     * @return array{games: list<array<string, mixed>>, byPlayers: list<array{players: int, plays: int}>, byYear: list<array{year: int, plays: int}>, averagePlayers: float|null}
     */
    public function statsForUser(User $user, array $filters = [], int $limit = 30): array
    {
        $conn = $this->getEntityManager()->getConnection();
        [$where, $params] = $this->where($user, $filters);
        $from = 'FROM game_session s JOIN game g ON g.id = s.game_id WHERE ' . $where;

        $games = $conn->fetchAllAssociative(
            'SELECT s.game_id AS id, g.name, COUNT(*) AS plays, ROUND(AVG(s.players_count), 1) AS avg_players,
                    MIN(s.players_count) AS min_players, MAX(s.players_count) AS max_players, MAX(s.played_at) AS last_played_at,
                    COUNT(*) FILTER (WHERE s.rating = 5) AS liked
             ' . $from . ' GROUP BY s.game_id, g.name ORDER BY plays DESC, g.name LIMIT ' . $limit,
            $params,
        );
        $ids = array_map(fn($g) => (int) $g['id'], $games);
        $split = [];
        if ($ids !== []) {
            $rows = $conn->fetchAllAssociative(
                'SELECT s.game_id, s.players_count, COUNT(*) AS plays ' . $from . ' AND s.game_id IN (' . implode(',', $ids) . ')
                 GROUP BY s.game_id, s.players_count ORDER BY s.players_count',
                $params,
            );
            foreach ($rows as $r) {
                $split[(int) $r['game_id']][] = ['players' => (int) $r['players_count'], 'plays' => (int) $r['plays']];
            }
        }

        $byPlayers = array_map(
            fn($r) => ['players' => (int) $r['players_count'], 'plays' => (int) $r['plays']],
            $conn->fetchAllAssociative('SELECT s.players_count, COUNT(*) AS plays ' . $from . ' GROUP BY s.players_count ORDER BY s.players_count', $params),
        );
        $byYear = array_map(
            fn($r) => ['year' => (int) $r['year'], 'plays' => (int) $r['plays']],
            $conn->fetchAllAssociative('SELECT EXTRACT(YEAR FROM s.played_at)::int AS year, COUNT(*) AS plays ' . $from . ' GROUP BY year ORDER BY year DESC', $params),
        );
        $avg = $conn->fetchOne('SELECT ROUND(AVG(s.players_count), 1) ' . $from, $params);

        return [
            'games' => array_map(fn($g) => [
                'id' => (int) $g['id'], 'name' => $g['name'], 'plays' => (int) $g['plays'],
                'averagePlayers' => (float) $g['avg_players'], 'minPlayers' => (int) $g['min_players'], 'maxPlayers' => (int) $g['max_players'],
                'lastPlayedAt' => (new \DateTimeImmutable($g['last_played_at']))->format('c'), 'liked' => (int) $g['liked'],
                'byPlayers' => $split[(int) $g['id']] ?? [],
            ], $games),
            'byPlayers' => $byPlayers,
            'byYear' => $byYear,
            'averagePlayers' => $avg === false || $avg === null ? null : (float) $avg,
        ];
    }
}
