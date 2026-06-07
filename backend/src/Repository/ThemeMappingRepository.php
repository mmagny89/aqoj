<?php

namespace App\Repository;

use App\Entity\ThemeMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

class ThemeMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Connection $conn)
    {
        parent::__construct($registry, ThemeMapping::class);
    }

    /** Toutes les entrées, triées par groupe puis label. */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.themeGroup', 'ASC')
            ->addOrderBy('t.themeLabel', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégories BGG présentes dans la table game.categories
     * qui n'ont pas encore de mapping.
     *
     * @return array<array{bgg_category: string, nb: int}>
     */
    public function findUnmappedCategories(): array
    {
        return $this->conn->fetchAllAssociative("
            SELECT sub.cat AS bgg_category, sub.nb
            FROM (
                SELECT json_array_elements_text(g.categories) AS cat,
                       COUNT(*) AS nb
                FROM game g
                WHERE g.categories::text != '[]'
                GROUP BY cat
            ) sub
            LEFT JOIN theme_mapping tm ON tm.bgg_category = sub.cat
            WHERE tm.id IS NULL
              AND sub.cat NOT IN ('Expansion for Base-game', 'Fan Expansion', 'Third-party Expansion')
            ORDER BY sub.nb DESC
        ");
    }
}
