<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findByCriteria(?int $players, ?int $maxTime, array $categories = [], array $mechanics = [], int $page = 1, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.isExpansion = false');

        if ($players !== null) {
            $qb->andWhere('g.minPlayers <= :players')
               ->andWhere('g.maxPlayers >= :players')
               ->setParameter('players', $players);
        }

        if ($maxTime !== null) {
            $qb->andWhere('(g.playingTime <= :maxTime OR g.playingTime = 0)')
               ->setParameter('maxTime', $maxTime);
        }

        return $qb
            ->orderBy('g.bggRank', 'ASC')
            ->addOrderBy('g.ratingBgg', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pool pour les recommandations : uniquement les jeux de la collection de l'utilisateur.
     * Les moteurs/joueurs/durée sont des filtres durs — le scoring gère le reste.
     *
     * @param string[] $familyKeys  filtre dur sur mechanic_families (engines + support) si non vide
     */
    public function findUserCollectionForRecommendation(
        User $user,
        ?int $players,
        ?int $maxTime,
        array $familyKeys = [],
        array $categoryKeys = [],
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $conditions = ['ug.user_id = ?', 'g.is_expansion = false'];
        $params = [$user->getId()];

        if (!empty($familyKeys)) {
            // Cherche dans mechanic_families (contient engines + support)
            $jsonClauses = array_map(fn($k) => 'g.mechanic_families::jsonb @> ?::jsonb', $familyKeys);
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
            foreach ($familyKeys as $key) {
                $params[] = json_encode([$key]);
            }
        }

        if (!empty($categoryKeys)) {
            $jsonClauses = array_map(fn($c) => 'g.categories::jsonb @> ?::jsonb', $categoryKeys);
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
            foreach ($categoryKeys as $cat) {
                $params[] = json_encode([$cat]);
            }
        }

        if ($players !== null) {
            $conditions[] = 'g.min_players <= ? AND g.max_players >= ?';
            $params[] = $players;
            $params[] = $players;
        }

        if ($maxTime !== null) {
            $conditions[] = '(g.playing_time <= ? OR g.playing_time = 0)';
            $params[] = $maxTime;
        }

        $where = implode(' AND ', $conditions);

        $ids = $conn->fetchFirstColumn(
            "SELECT g.id FROM game g
             INNER JOIN user_game ug ON ug.game_id = g.id
             WHERE {$where}",
            $params
        );

        if (empty($ids)) {
            return [];
        }

        $games = $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($games as $game) {
            $indexed[$game->getId()] = $game;
        }

        return array_values(array_filter(
            array_map(fn(int $id) => $indexed[$id] ?? null, array_map('intval', $ids))
        ));
    }

    /**
     * Jeux hors collection de l'utilisateur, filtrés par critères.
     * Triés par note BGG décroissante — scoring simple côté service.
     *
     * @param string[] $familyKeys filtre dur sur mechanic_families si non vide
     */
    public function findDiscoverForRecommendation(
        User $user,
        ?int $players,
        ?int $maxTime,
        array $familyKeys = [],
        int $limit = 50,
        ?int $minYear = null,
        ?int $maxYear = null,
        array $categoryKeys = [],
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $conditions = [
            'g.is_expansion = false',
            'g.mechanics::text != \'[]\'',          // jeux enrichis uniquement
            'g.rating_bgg IS NOT NULL',
            'g.rating_bgg >= 6.5',
            // Exclure les jeux de la collection
            'NOT EXISTS (SELECT 1 FROM user_game ug WHERE ug.game_id = g.id AND ug.user_id = ?)',
            // Exclure les rééditions/versions collector de jeux déjà en collection.
            // implements_bgg_ids est un tableau jsonb de bgg_id (strings).
            // On utilise EXISTS + jsonb_array_elements_text car && n'existe pas sur jsonb.
            "NOT EXISTS (
                SELECT 1
                FROM jsonb_array_elements_text(g.implements_bgg_ids) impl_id
                JOIN game g2 ON g2.bgg_id = impl_id
                JOIN user_game ug2 ON ug2.game_id = g2.id AND ug2.user_id = ?
            )",
        ];
        $params = [$user->getId(), $user->getId()];

        if (!empty($familyKeys)) {
            $jsonClauses = array_map(fn($k) => 'g.mechanic_families::jsonb @> ?::jsonb', $familyKeys);
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
            foreach ($familyKeys as $key) {
                $params[] = json_encode([$key]);
            }
        }

        if ($players !== null) {
            $conditions[] = 'g.min_players <= ? AND g.max_players >= ?';
            $params[] = $players;
            $params[] = $players;
        }

        if ($maxTime !== null) {
            $conditions[] = '(g.playing_time <= ? OR g.playing_time = 0)';
            $params[] = $maxTime;
        }

        if ($minYear !== null) {
            $conditions[] = 'g.year_published >= ?';
            $params[] = $minYear;
        }

        if ($maxYear !== null) {
            $conditions[] = 'g.year_published <= ?';
            $params[] = $maxYear;
        }

        if (!empty($categoryKeys)) {
            $jsonClauses = array_map(fn($c) => 'g.categories::jsonb @> ?::jsonb', $categoryKeys);
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
            foreach ($categoryKeys as $cat) {
                $params[] = json_encode([$cat]);
            }
        }

        $where = implode(' AND ', $conditions);
        $params[] = $limit;

        $ids = $conn->fetchFirstColumn(
            "SELECT g.id FROM game g
             WHERE {$where}
             ORDER BY g.rating_bgg DESC NULLS LAST
             LIMIT ?",
            $params
        );

        if (empty($ids)) {
            return [];
        }

        $games = $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($games as $game) {
            $indexed[$game->getId()] = $game;
        }

        return array_values(array_filter(
            array_map(fn(int $id) => $indexed[$id] ?? null, array_map('intval', $ids))
        ));
    }

    public function findExistingBggIds(array $bggIds): array
    {
        if (empty($bggIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('g')
            ->select('g.bggId')
            ->where('g.bggId IN (:ids)')
            ->setParameter('ids', $bggIds)
            ->getQuery()
            ->getArrayResult();

        return array_column($results, 'bggId');
    }

    /**
     * Recherche textuelle pleine — inclut les extensions et promos, pas de limite arbitraire.
     * Tri : d'abord les jeux de base (isExpansion=false) bien notés, puis les extensions.
     */
    public function searchByName(string $query, int $limit = 200): array
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . addcslashes($query, '%_') . '%')
            // Jeux de base en premier, puis par note BGG décroissante
            ->orderBy('g.isExpansion', 'ASC')
            ->addOrderBy('g.ratingBgg', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByBggIds(array $bggIds): array
    {
        if (empty($bggIds)) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->where('g.bggId IN (:ids)')
            ->setParameter('ids', $bggIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param bool|null $played  true = déjà notés (bgg_user_rating IS NOT NULL),
     *                            false = pas encore notés,
     *                            null = tous
     */
    public function findByUserCollection(User $user, int $page = 1, int $limit = 50, ?bool $played = null): array
    {
        $playedClause = match ($played) {
            true  => 'AND ug.bgg_user_rating IS NOT NULL',
            false => 'AND ug.bgg_user_rating IS NULL',
            null  => '',
        };

        // DBAL pour la pagination : le JOIN ManyToMany + setFirstResult de Doctrine ORM
        // génère une sous-requête qui casse le filtre. On récupère les IDs via SQL natif.
        // Joués → par note BGG utilisateur décroissante, puis note communauté
        // Pas encore joués → ordre alphabétique
        // Tous → rang BGG croissant
        $orderBy = match ($played) {
            true  => 'ug.bgg_user_rating DESC NULLS LAST, g.rating_bgg DESC NULLS LAST',
            false => 'g.name ASC',
            null  => 'g.bgg_rank ASC NULLS LAST, g.rating_bgg DESC NULLS LAST',
        };

        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT ug.game_id
             FROM user_game ug
             JOIN game g ON g.id = ug.game_id
             WHERE ug.user_id = ? {$playedClause}
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            [$user->getId(), $limit, ($page - 1) * $limit]
        );

        if (empty($ids)) {
            return [];
        }

        $games = $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Réordonne selon l'ordre de la requête DBAL
        $indexed = [];
        foreach ($games as $game) {
            $indexed[$game->getId()] = $game;
        }

        return array_values(array_filter(array_map(
            fn(int $id) => $indexed[$id] ?? null,
            array_map('intval', $ids)
        )));
    }

    public function countUserCollection(User $user, ?bool $played = null): int
    {
        $playedClause = match ($played) {
            true  => 'AND bgg_user_rating IS NOT NULL',
            false => 'AND bgg_user_rating IS NULL',
            null  => '',
        };

        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM user_game WHERE user_id = ? {$playedClause}",
            [$user->getId()]
        );
    }

    /**
     * Jeux en attente de premier enrichissement (source = bgg_csv, jamais passés par l'API BGG).
     * Priorité : rang BGG croissant (les jeux populaires d'abord).
     */
    public function findPendingEnrichmentIds(int $limit): array
    {
        $sql = 'SELECT bgg_id FROM game WHERE source = ? AND bgg_id IS NOT NULL
                ORDER BY bgg_rank ASC NULLS LAST, rating_bgg DESC NULLS LAST';
        $params = ['bgg_csv'];

        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }

        return $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $params);
    }

    /**
     * Jeux à resyncer (source = bgg_api) : jamais synchronisés OU les plus anciens en premier.
     * Utilisé par la tâche cron de resync quotidien incrémental.
     *
     * @return string[]  bggIds
     */
    public function findStaleForResync(int $limit): array
    {
        $sql = "
            SELECT bgg_id FROM game
            WHERE source = 'bgg_api'
              AND bgg_id IS NOT NULL
            ORDER BY last_synced_at ASC NULLS FIRST
        ";
        $params = [];

        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }

        return $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $params);
    }

    /**
     * Vérifie si un bggId est déjà présent en base.
     */
    public function existsByBggId(string $bggId): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT 1 FROM game WHERE bgg_id = ?',
            [$bggId]
        );
    }

    /**
     * Filtre par moteur(s) central(aux) (colonne JSON detected_engines) + critères optionnels.
     * Utilise DBAL pour le @> opérateur PostgreSQL sur jsonb.
     *
     * @param string[] $engineKeys  ex: ['worker_placement', 'deck_building']
     */
    public function findByEngines(array $engineKeys, ?int $players, ?int $maxTime, int $page = 1, int $limit = 50): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $conditions = ['is_expansion = false'];
        $params = [];

        if (!empty($engineKeys)) {
            $jsonClauses = [];
            foreach ($engineKeys as $key) {
                $jsonClauses[] = 'detected_engines::jsonb @> ?::jsonb';
                $params[] = json_encode([$key]);
            }
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
        }

        if ($players !== null) {
            $conditions[] = 'min_players <= ? AND max_players >= ?';
            $params[] = $players;
            $params[] = $players;
        }

        if ($maxTime !== null) {
            $conditions[] = '(playing_time <= ? OR playing_time = 0)';
            $params[] = $maxTime;
        }

        $where = implode(' AND ', $conditions);
        $offset = ($page - 1) * $limit;
        $params[] = $limit;
        $params[] = $offset;

        $ids = $conn->fetchFirstColumn(
            "SELECT id FROM game WHERE {$where}
             ORDER BY bgg_rank ASC NULLS LAST, rating_bgg DESC NULLS LAST
             LIMIT ? OFFSET ?",
            $params
        );

        if (empty($ids)) {
            return [];
        }

        // Recharge les entités en préservant l'ordre DBAL
        $games = $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($games as $game) {
            $indexed[$game->getId()] = $game;
        }

        return array_values(array_filter(
            array_map(fn(int $id) => $indexed[$id] ?? null, array_map('intval', $ids))
        ));
    }

    public function findNotPlayedRecently(int $daysThreshold = 180, int $limit = 10): array
    {
        $threshold = new \DateTimeImmutable("-{$daysThreshold} days");

        return $this->createQueryBuilder('g')
            ->leftJoin('App\Entity\GameSession', 's', 'WITH', 's.game = g')
            ->where('s.id IS NULL OR s.playedAt < :threshold')
            ->andWhere('g.ratingBgg >= 7.0')
            ->setParameter('threshold', $threshold)
            ->orderBy('g.ratingBgg', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
