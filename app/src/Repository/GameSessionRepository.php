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

    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.playedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
}
