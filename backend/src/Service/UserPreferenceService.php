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
     * Signal fort = jeux bien notés (BGG ≥ 6)
     * Signal moyen = sessions aimées (rating = 5)
     * Signal faible = collection entière
     *
     * @return array<string, float>  famille => score, trié desc
     */
    private function computeFamilyScores(User $user): array
    {
        $scores = [];

        // ── Signal fort : jeux bien notés sur BGG ──────────────────────
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, SUM(bgg_user_rating) AS weight
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key,
                        ug.bgg_user_rating
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ?
                   AND ug.bgg_user_rating >= 6
                   AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key",
            [$user->getId()]
        );
        foreach ($rows as $row) {
            $scores[$row['family_key']] = ($scores[$row['family_key']] ?? 0) + (float) $row['weight'] * 3.0;
        }

        // ── Signal moyen : sessions aimées ──────────────────────────────
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, COUNT(*) AS cnt
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key
                 FROM game_session gs
                 JOIN game g ON g.id = gs.game_id
                 WHERE gs.user_id = ?
                   AND gs.rating = 5
                   AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key",
            [$user->getId()]
        );
        foreach ($rows as $row) {
            $scores[$row['family_key']] = ($scores[$row['family_key']] ?? 0) + (int) $row['cnt'] * 2.0;
        }

        // ── Signal faible : collection hors jeux détestés ──────────────
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, COUNT(*) AS cnt
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ?
                   AND (ug.bgg_user_rating IS NULL OR ug.bgg_user_rating >= 5)
                   AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key",
            [$user->getId()]
        );
        foreach ($rows as $row) {
            $scores[$row['family_key']] = ($scores[$row['family_key']] ?? 0) + (int) $row['cnt'] * 1.0;
        }

        arsort($scores);
        return $scores;
    }

    /**
     * Top catégories BGG d'après les jeux aimés.
     * @return string[]
     */
    private function computeTopCategories(User $user): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT cat, SUM(weight) AS total
             FROM (
                 -- Jeux bien notés BGG
                 SELECT json_array_elements_text(g.categories) AS cat,
                        ug.bgg_user_rating AS weight
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ? AND ug.bgg_user_rating >= 6 AND g.categories::text != '[]'
                 UNION ALL
                 -- Sessions aimées
                 SELECT json_array_elements_text(g.categories) AS cat,
                        5 AS weight
                 FROM game_session gs
                 JOIN game g ON g.id = gs.game_id
                 WHERE gs.user_id = ? AND gs.rating = 5 AND g.categories::text != '[]'
             ) sub
             GROUP BY cat
             ORDER BY total DESC
             LIMIT 8",
            [$user->getId(), $user->getId()]
        );

        return array_column($rows, 'cat');
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
