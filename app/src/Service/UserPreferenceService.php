<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\UserPreferenceRepository;
use App\Utils\EngelsteinFamilies;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class UserPreferenceService
{
    public function __construct(
        private readonly Connection $conn,
        private readonly UserPreferenceRepository $prefRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Recalcule et sauvegarde les préférences mécaniques de l'utilisateur.
     * À appeler après un import BGG ou une nouvelle partie aimée.
     */
    public function recompute(User $user): UserPreference
    {
        $pref = $this->prefRepository->findOrCreate($user);

        $families   = $this->computeFamilyScores($user);
        $categories = $this->computeTopCategories($user);

        // Séparer moteurs, support et extra d'après les constantes Engelstein
        $engines = [];
        $support = [];
        foreach ($families as $key => $score) {
            if (in_array($key, self::ENGINE_FAMILIES, true)) {
                $engines[$key] = $score;
            } else {
                $support[$key] = $score;
            }
        }

        $pref->setRankedFamilies(array_keys($families))
             ->setTopEngines(array_keys(array_slice($engines, 0, 3, true)))
             ->setTopSupport(array_keys(array_slice($support, 0, 4, true)))
             ->setTopCategories(array_slice($categories, 0, 8))
             ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($pref);
        $this->em->flush();

        return $pref;
    }

    /**
     * Calcule le score de chaque famille pour l'utilisateur.
     *
     * Trois signaux, à des échelles comparables pour qu'une collection MyLudo, souvent sans notes,
     * pèse autant qu'une collection BGG notée :
     *   - jeu noté 6 ou plus : `(note - 5) × 2` points (6 → 2, 8 → 6, 10 → 10)
     *   - partie aimée       : 2 points
     *   - jeu possédé, non détesté (sans note, ou noté 5 ou plus) : 1 point, que la note vienne de BGG ou de MyLudo
     *
     * @return array<string, float>  famille => score, trié desc
     */
    private function computeFamilyScores(User $user): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, SUM(points) AS score
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key,
                        CASE WHEN ug.bgg_user_rating >= 6 THEN (ug.bgg_user_rating - 5) * 2 ELSE 0 END
                        + CASE WHEN ug.bgg_user_rating IS NULL OR ug.bgg_user_rating >= 5 THEN 1 ELSE 0 END AS points
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ? AND g.mechanic_families::text != '[]'
                 UNION ALL
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key, 2 AS points
                 FROM game_session gs
                 JOIN game g ON g.id = gs.game_id
                 WHERE gs.user_id = ? AND gs.rating = 5 AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key",
            [$user->getId(), $user->getId()]
        );

        $scores = [];
        foreach ($rows as $row) {
            $scores[$row['family_key']] = (float) $row['score'];
        }

        arsort($scores);
        return $scores;
    }

    /**
     * Top catégories BGG, avec les mêmes signaux et les mêmes échelles que les familles.
     * @return string[]
     */
    private function computeTopCategories(User $user): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT cat, SUM(points) AS total
             FROM (
                 SELECT json_array_elements_text(g.categories) AS cat,
                        CASE WHEN ug.bgg_user_rating >= 6 THEN (ug.bgg_user_rating - 5) * 2 ELSE 0 END
                        + CASE WHEN ug.bgg_user_rating IS NULL OR ug.bgg_user_rating >= 5 THEN 1 ELSE 0 END AS points
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ? AND g.categories::text != '[]'
                 UNION ALL
                 SELECT json_array_elements_text(g.categories) AS cat, 2 AS points
                 FROM game_session gs
                 JOIN game g ON g.id = gs.game_id
                 WHERE gs.user_id = ? AND gs.rating = 5 AND g.categories::text != '[]'
             ) sub
             -- « Expansion for Base-game » décrit le type de fiche, pas un thème : elle ne dit rien des goûts.
             WHERE cat <> 'Expansion for Base-game'
             GROUP BY cat
             ORDER BY total DESC, cat
             LIMIT 8",
            [$user->getId(), $user->getId()]
        );

        return array_column($rows, 'cat');
    }

    /**
     * Combien de jeux de la collection ont servi au calcul : un jeu importé de MyLudo n'entre dans les styles
     * qu'une fois sa fiche enrichie (mécaniques connues), ce que le planificateur fait au fil des nuits.
     *
     * @return array{total: int, analysed: int}
     */
    public function coverage(User $user): array
    {
        $row = $this->conn->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE g.mechanic_families::text != '[]') AS analysed
             FROM user_game ug JOIN game g ON g.id = ug.game_id
             WHERE ug.user_id = ? AND g.is_expansion = false",
            [$user->getId()]
        );

        return ['total' => (int) $row['total'], 'analysed' => (int) $row['analysed']];
    }

    // Familles Engelstein identifiées comme moteurs (pour la séparation engines/support)
    private const ENGINE_FAMILIES = [
        'worker_placement',
        'deck_building',
        'engine_building',
        'area_control',
        'hand_management',
        'auction',
    ];
}
