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

    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . addcslashes($query, '%_') . '%')
            ->orderBy('g.ratingBgg', 'DESC')
            ->setMaxResults(20)
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

    public function findByUserCollection(User $user, int $page = 1, int $limit = 50): array
    {
        // DBAL pour la pagination : le JOIN ManyToMany + setFirstResult de Doctrine ORM
        // génère une sous-requête qui casse le filtre. On récupère les IDs via SQL natif.
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT ug.game_id
             FROM user_game ug
             JOIN game g ON g.id = ug.game_id
             WHERE ug.user_id = ?
             ORDER BY g.bgg_rank ASC NULLS LAST, g.rating_bgg DESC NULLS LAST
             LIMIT ? OFFSET ?',
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

    public function countUserCollection(User $user): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM user_game WHERE user_id = ?',
            [$user->getId()]
        );
    }

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
