<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GameRepository extends ServiceEntityRepository
{
    /** Jeu présent dans la ludothèque de l'association ou dans celle d'au moins un membre. */
    public const IN_LIBRARY = '(EXISTS (SELECT 1 FROM association_game ag WHERE ag.game_id = g.id)
        OR EXISTS (SELECT 1 FROM user_game ugl WHERE ugl.game_id = g.id))';

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
        $conditions = ['ug.user_id = ?'];
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
     * @param bool     $library    true : seulement les jeux de la ludothèque de l'association ou d'un membre
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
        array $excludeIds = [],
        bool $baseGamesOnly = false,
        ?float $maxComplexity = null,
        ?int $minVotes = null,
        bool $library = false,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $conditions = [];
        $params     = [];

        if ($library) {
            // Ludothèque de l'association et des membres : on y joue, donc la collection du joueur n'est pas exclue.
            // Seule l'appartenance sort, jamais le propriétaire.
            $conditions[] = self::IN_LIBRARY;
            // Une ludothèque est petite : aucun seuil de note, de votes ni de mécaniques n'écarte un jeu qu'on peut sortir.
            // Un filtre de familles n'y est posé que si l'appelant en passe un (choix explicite du joueur).
            $minVotes = null;
        } else {
            $conditions[] = 'g.mechanics::text != \'[]\'';          // jeux enrichis uniquement
            $conditions[] = 'g.rating_bgg IS NOT NULL';
            $conditions[] = 'g.rating_bgg >= 6.5';
            $conditions[] = 'NOT EXISTS (SELECT 1 FROM user_game ug WHERE ug.game_id = g.id AND ug.user_id = ?)';
            // Exclure les rééditions/versions collector de jeux déjà en collection.
            // implements_bgg_ids est un tableau jsonb de bgg_id (strings).
            // On utilise EXISTS + jsonb_array_elements_text car && n'existe pas sur jsonb.
            $conditions[] = "NOT EXISTS (
                SELECT 1
                FROM jsonb_array_elements_text(g.implements_bgg_ids) impl_id
                JOIN game g2 ON g2.bgg_id = impl_id
                JOIN user_game ug2 ON ug2.game_id = g2.id AND ug2.user_id = ?
            )";
            array_push($params, $user->getId(), $user->getId());
        }

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

        if ($baseGamesOnly) {
            $conditions[] = 'g.is_expansion = false';
        }

        if ($maxComplexity !== null) {
            // 0 = complexité inconnue : on ne la présente pas comme « accessible »
            $conditions[] = 'g.complexity > 0 AND g.complexity <= ?';
            $params[] = $maxComplexity;
        }

        if ($minVotes !== null) {
            $conditions[] = 'g.users_rated >= ?';
            $params[] = $minVotes;
        }

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $conditions[] = "g.id NOT IN ({$placeholders})";
            foreach ($excludeIds as $id) {
                $params[] = $id;
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

    /** Jeux de base de la ludothèque de l'association et des membres, sans doublon. */
    public function findLibraryGames(): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT g.id FROM game g WHERE g.is_expansion = false AND ' . self::IN_LIBRARY
        );
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
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
        // Titre affiché (français si connu) et titre original, sans accents ni casse.
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT id FROM game
             WHERE unaccent(LOWER(name)) LIKE unaccent(LOWER(:q))
                OR unaccent(LOWER(COALESCE(name_original, ''))) LIKE unaccent(LOWER(:q))
             -- Le titre exact d'abord, puis ceux qui commencent par la saisie, puis le reste ;
             -- à égalité, jeux de base avant extensions, puis note BGG décroissante
             ORDER BY (unaccent(LOWER(name)) = unaccent(LOWER(:exact)) OR unaccent(LOWER(COALESCE(name_original, ''))) = unaccent(LOWER(:exact))) DESC,
                      (unaccent(LOWER(name)) LIKE unaccent(LOWER(:prefix)) OR unaccent(LOWER(COALESCE(name_original, ''))) LIKE unaccent(LOWER(:prefix))) DESC,
                      is_expansion ASC, rating_bgg DESC NULLS LAST
             LIMIT :limit",
            [
                'q'      => '%' . addcslashes($query, '%_\\') . '%',
                'exact'  => $query,
                'prefix' => addcslashes($query, '%_\\') . '%',
                'limit'  => $limit,
            ],
        );
        if ($ids === []) {
            return [];
        }

        $games = [];
        foreach ($this->findBy(['id' => $ids]) as $game) {
            $games[$game->getId()] = $game;
        }

        return array_values(array_filter(array_map(fn($id) => $games[$id] ?? null, $ids)));
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
    public function findByUserCollection(User $user, int $page = 1, int $limit = 50, ?bool $played = null, ?bool $isExpansion = null): array
    {
        $playedClause = match ($played) {
            true  => 'AND ug.bgg_user_rating IS NOT NULL',
            false => 'AND ug.bgg_user_rating IS NULL',
            null  => '',
        };

        $expansionClause = match ($isExpansion) {
            true  => 'AND g.is_expansion = TRUE',
            false => 'AND g.is_expansion = FALSE',
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
             WHERE ug.user_id = ? {$playedClause} {$expansionClause}
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

    public function countUserCollection(User $user, ?bool $played = null, ?bool $isExpansion = null): int
    {
        $playedClause = match ($played) {
            true  => 'AND ug.bgg_user_rating IS NOT NULL',
            false => 'AND ug.bgg_user_rating IS NULL',
            null  => '',
        };

        $expansionClause = match ($isExpansion) {
            true  => 'AND g.is_expansion = TRUE',
            false => 'AND g.is_expansion = FALSE',
            null  => '',
        };

        $joinNeeded = $isExpansion !== null;
        if ($joinNeeded) {
            return (int) $this->getEntityManager()->getConnection()->fetchOne(
                "SELECT COUNT(*) FROM user_game ug JOIN game g ON g.id = ug.game_id WHERE ug.user_id = ? {$playedClause} {$expansionClause}",
                [$user->getId()]
            );
        }

        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM user_game WHERE user_id = ? {$playedClause}",
            [$user->getId()]
        );
    }

    /**
     * Jeux du CSV à enrichir pour la première fois. Les jeux déjà demandés à BGG
     * depuis moins de 7 jours (absents de la réponse, ou lot en erreur) sont
     * écartés ; ceux que des joueurs possèdent passent avant le reste du classement.
     *
     * @return string[]  bggIds
     */
    public function findPendingEnrichmentIds(int $limit): array
    {
        $sql = "SELECT g.bgg_id FROM game g
                WHERE g.source = 'bgg_csv' AND g.bgg_id IS NOT NULL
                  AND (g.last_fetch_attempt_at IS NULL OR g.last_fetch_attempt_at < NOW() - INTERVAL '7 days')
                ORDER BY (EXISTS (SELECT 1 FROM user_game ug WHERE ug.game_id = g.id)
                          OR EXISTS (SELECT 1 FROM association_game ag WHERE ag.game_id = g.id)) DESC,
                         g.bgg_rank ASC NULLS LAST, g.rating_bgg DESC NULLS LAST";
        $params = [];

        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }

        return $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $params);
    }

    /**
     * Jeux à resyncer (source = bgg_api) : seulement ceux dont la dernière synchro
     * est périmée — 30 jours pour un jeu classé, 90 pour les autres — les plus
     * anciens d'abord. Un jeu déjà demandé depuis moins de 7 jours est écarté.
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
              AND (last_synced_at IS NULL
                   OR last_synced_at < NOW() - CASE WHEN bgg_rank IS NOT NULL THEN INTERVAL '30 days' ELSE INTERVAL '90 days' END)
              AND (last_fetch_attempt_at IS NULL OR last_fetch_attempt_at < NOW() - INTERVAL '7 days')
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
     * Date l'appel BGG de ces jeux, qu'il ait rapporté quelque chose ou non.
     *
     * @param string[] $bggIds
     */
    public function markFetchAttempted(array $bggIds): void
    {
        if ($bggIds === []) {
            return;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE game SET last_fetch_attempt_at = NOW() WHERE bgg_id IN (?)',
            [$bggIds],
            [ArrayParameterType::STRING],
        );
    }

    /**
     * Extensions citées par des jeux déjà enrichis mais absentes de la base.
     *
     * @return string[]  bggIds
     */
    public function findMissingExpansionBggIds(int $limit): array
    {
        return $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT e.bgg_id
             FROM game g, jsonb_array_elements_text(g.expansion_bgg_ids::jsonb) AS e(bgg_id)
             WHERE NOT EXISTS (SELECT 1 FROM game x WHERE x.bgg_id = e.bgg_id)
             LIMIT ?",
            [$limit],
        );
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

    /**
     * Trouve des candidats similaires à un jeu de référence.
     * Filtre sur les familles mécaniques pour ne pas scanner toute la table.
     *
     * @return Game[]
     */
    public function findSimilarCandidates(Game $ref, int $limit = 30): array
    {
        $families = $ref->getMechanicFamilies();
        $conn     = $this->getEntityManager()->getConnection();

        $conditions = [
            'g.id != ' . (int) $ref->getId(),
            'g.is_expansion = false',
            'g.rating_bgg IS NOT NULL',
            'g.rating_bgg >= 5.5',
            'g.mechanics::text != \'[]\'',
        ];
        $params = [];

        if (!empty($families)) {
            $jsonClauses = array_map(fn($k) => 'g.mechanic_families::jsonb @> ?::jsonb', $families);
            $conditions[] = '(' . implode(' OR ', $jsonClauses) . ')';
            foreach ($families as $key) {
                $params[] = json_encode([$key]);
            }
        }

        $params[] = $limit;
        $where = implode(' AND ', $conditions);

        $ids = $conn->fetchFirstColumn(
            "SELECT g.id FROM game g WHERE {$where} ORDER BY g.rating_bgg DESC NULLS LAST LIMIT ?",
            $params
        );

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
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
