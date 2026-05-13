<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findByCriteria(?int $players, ?int $maxTime, array $categories = [], array $mechanics = []): array
    {
        $qb = $this->createQueryBuilder('g');

        if ($players !== null) {
            $qb->andWhere('g.minPlayers <= :players')
               ->andWhere('g.maxPlayers >= :players')
               ->setParameter('players', $players);
        }

        if ($maxTime !== null) {
            $qb->andWhere('g.playingTime <= :maxTime OR g.playingTime = 0')
               ->setParameter('maxTime', $maxTime);
        }

        return $qb->orderBy('g.ratingBgg', 'DESC')->getQuery()->getResult();
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
