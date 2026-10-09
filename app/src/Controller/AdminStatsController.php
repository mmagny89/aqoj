<?php

namespace App\Controller;

use App\Service\JwtService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AdminStatsController extends AbstractController
{
    #[Route('/api/admin/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(
        Request $request,
        JwtService $jwt,
        EntityManagerInterface $em,
        Connection $conn,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user || !$user->isAdmin()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // ── Jeux ─────────────────────────────────────────────────────────────
        $games = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                                    AS total,
                COUNT(*) FILTER (WHERE source = 'bgg_api')                 AS enriched,
                COUNT(*) FILTER (WHERE source = 'bgg_csv')                 AS pending,
                COUNT(*) FILTER (WHERE mechanics::text = '[]')             AS no_mechanics,
                COUNT(*) FILTER (WHERE image_url IS NULL)                  AS no_image,
                COUNT(*) FILTER (WHERE description IS NULL)                AS no_description,
                COUNT(*) FILTER (WHERE rating_bgg IS NULL)                 AS no_rating,
                COUNT(*) FILTER (WHERE bgg_rank IS NULL AND source='bgg_api') AS no_rank,
                MAX(last_synced_at)                                        AS last_synced,
                MIN(last_synced_at) FILTER (WHERE source = 'bgg_api')     AS oldest_synced,
                COUNT(*) FILTER (
                    WHERE source = 'bgg_api'
                    AND (last_synced_at IS NULL OR last_synced_at < NOW() - INTERVAL '30 days')
                )                                                           AS stale_count
            FROM game
            WHERE is_expansion = false
        ");

        // ── Extensions ───────────────────────────────────────────────────────
        $expansions = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                                        AS total,
                COUNT(*) FILTER (WHERE source = 'bgg_api')                     AS enriched,
                COUNT(*) FILTER (WHERE source = 'bgg_csv')                     AS pending,
                COUNT(*) FILTER (WHERE implements_bgg_ids::text != '[]')       AS linked,
                COUNT(*) FILTER (WHERE implements_bgg_ids::text  = '[]')       AS unlinked,
                COUNT(*) FILTER (WHERE mechanics::text = '[]')                 AS no_mechanics,
                COUNT(*) FILTER (WHERE image_url IS NULL)                      AS no_image,
                COUNT(*) FILTER (
                    WHERE source = 'bgg_api'
                    AND (last_synced_at IS NULL OR last_synced_at < NOW() - INTERVAL '30 days')
                )                                                               AS stale_count
            FROM game
            WHERE is_expansion = true
        ");

        // ── Utilisateurs ─────────────────────────────────────────────────────
        $users = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                              AS total,
                COUNT(*) FILTER (WHERE bgg_username IS NOT NULL)     AS with_bgg,
                COUNT(*) FILTER (WHERE bgg_username IS NULL)         AS without_bgg,
                COUNT(*) FILTER (WHERE is_admin = true)              AS admins
            FROM app_user
        ");

        $collections = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                             AS total_links,
                COUNT(DISTINCT user_id)                              AS users_with_games,
                ROUND(AVG(per_user.cnt), 1)                         AS avg_per_user,
                MAX(per_user.cnt)                                    AS max_per_user,
                COUNT(*) FILTER (WHERE bgg_user_rating IS NOT NULL)  AS rated_links
            FROM user_game
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS cnt FROM user_game GROUP BY user_id
            ) per_user USING (user_id)
        ");

        // ── Parties ──────────────────────────────────────────────────────────
        $sessions = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                                    AS total,
                COUNT(*) FILTER (WHERE played_at >= DATE_TRUNC('week',  NOW())) AS this_week,
                COUNT(*) FILTER (WHERE played_at >= DATE_TRUNC('month', NOW())) AS this_month,
                COUNT(*) FILTER (WHERE played_at >= DATE_TRUNC('year',  NOW())) AS this_year,
                COUNT(*) FILTER (WHERE rating IS NOT NULL)                  AS with_rating,
                COUNT(DISTINCT game_id)                                     AS distinct_games,
                COUNT(DISTINCT user_id)                                     AS distinct_users,
                MAX(played_at)                                              AS last_session
            FROM game_session
        ");

        // ── Mappings mécaniques ───────────────────────────────────────────────
        $mappings = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                AS total_mappings,
                COUNT(DISTINCT engelstein_family)       AS families_used
            FROM mechanic_mapping
        ");

        $unmapped = $conn->fetchOne("
            SELECT COUNT(DISTINCT sub.mechanic)
            FROM (
                SELECT json_array_elements_text(mechanics) AS mechanic
                FROM game WHERE mechanics::text != '[]'
            ) sub
            LEFT JOIN mechanic_mapping mm ON mm.bgg_mechanic = sub.mechanic
            WHERE mm.bgg_mechanic IS NULL
        ");

        // ── Mappings thématiques ──────────────────────────────────────────────
        $themes = $conn->fetchAssociative("
            SELECT
                COUNT(*)                            AS total_mappings,
                COUNT(DISTINCT theme_label)         AS distinct_labels,
                COUNT(DISTINCT theme_group)         AS distinct_groups
            FROM theme_mapping
        ");

        $unmappedThemes = $conn->fetchOne("
            SELECT COUNT(DISTINCT sub.category)
            FROM (
                SELECT json_array_elements_text(categories) AS category
                FROM game WHERE categories::text != '[]'
            ) sub
            LEFT JOIN theme_mapping tm ON tm.bgg_category = sub.category
            WHERE tm.bgg_category IS NULL
        ");

        return $this->json([
            'games'      => $games,
            'expansions' => $expansions,
            'users'      => array_merge($users, ['collections' => $collections]),
            'sessions'   => $sessions,
            'mappings'   => array_merge($mappings, ['unmapped_bgg_mechanics' => (int) $unmapped]),
            'themes'     => array_merge($themes,   ['unmapped_bgg_categories' => (int) $unmappedThemes]),
            'generated_at' => (new \DateTimeImmutable())->format('c'),
        ]);
    }
}
