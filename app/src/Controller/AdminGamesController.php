<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Explorateur du catalogue pour l'administration : où en est chaque fiche (importée,
 * enrichie, périmée, jamais demandée à BGG…). Lecture seule, aucun appel à BGG.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminGamesController extends AbstractController
{
    private const PER_PAGE = 50;

    /**
     * Filtres nommés : fragments SQL constants, jamais construits depuis la requête.
     * Les règles « périmé » et « en attente » reprennent celles de GameRepository (findStaleForResync,
     * findPendingEnrichmentIds) : si l'une change, l'autre doit suivre.
     */
    private const FILTERS = [
        'all' => 'TRUE',
        'enriched' => "source = 'bgg_api'",
        'pending' => "source = 'bgg_csv'",
        'never_attempted' => "source = 'bgg_csv' AND last_fetch_attempt_at IS NULL",
        'retry_later' => "source = 'bgg_csv' AND last_fetch_attempt_at >= NOW() - INTERVAL '7 days'",
        'unanswered' => "source = 'bgg_csv' AND last_fetch_attempt_at IS NOT NULL",
        'stale' => "source = 'bgg_api' AND (last_synced_at IS NULL OR last_synced_at < NOW() - CASE WHEN bgg_rank IS NOT NULL THEN INTERVAL '30 days' ELSE INTERVAL '90 days' END)",
        'no_image' => "source = 'bgg_api' AND image_url IS NULL",
        'no_description' => "source = 'bgg_api' AND description IS NULL",
        'no_mechanics' => "source = 'bgg_api' AND mechanics::text = '[]'",
        'unlinked_expansions' => "is_expansion = TRUE AND implements_bgg_ids::text = '[]'",
    ];

    private const SORTS = [
        'rank' => 'bgg_rank ASC NULLS LAST, id',
        'name' => 'name ASC, id',
        'synced' => 'last_synced_at DESC NULLS LAST, id',
        'attempted' => 'last_fetch_attempt_at DESC NULLS LAST, id',
        'created' => 'created_at DESC, id',
    ];

    private const COLUMNS = 'bgg_id, name, name_original, year_published, bgg_rank, users_rated, rating_bgg, is_expansion, source, last_synced_at, last_fetch_attempt_at, created_at, (image_url IS NOT NULL) AS has_image, (description IS NOT NULL) AS has_description';

    #[Route('/api/admin/games/filters', name: 'api_admin_games_filters', methods: ['GET'])]
    public function filters(Connection $conn): JsonResponse
    {
        $select = [];
        foreach (self::FILTERS as $name => $where) {
            $select[] = \sprintf('COUNT(*) FILTER (WHERE %s) AS "%s"', $where, $name);
        }
        $counts = $conn->fetchAssociative('SELECT '.implode(', ', $select).' FROM game') ?: [];

        return $this->json(array_map('intval', $counts));
    }

    #[Route('/api/admin/games', name: 'api_admin_games', methods: ['GET'])]
    public function list(Request $request, Connection $conn): JsonResponse
    {
        $filter = $request->query->getString('filter', 'all');
        $sort = $request->query->getString('sort', 'rank');
        if (!isset(self::FILTERS[$filter]) || !isset(self::SORTS[$sort])) {
            return $this->json(['error' => 'Filtre ou tri inconnu.'], 400);
        }
        $page = max(1, $request->query->getInt('page', 1));
        $term = trim($request->query->getString('q'));

        $where = self::FILTERS[$filter];
        $params = [];
        if ('' !== $term) {
            $where .= ' AND (unaccent(lower(name)) LIKE unaccent(lower(?)) OR unaccent(lower(COALESCE(name_original, \'\'))) LIKE unaccent(lower(?)) OR bgg_id = ?)';
            $like = '%'.addcslashes($term, '%_\\').'%';
            array_push($params, $like, $like, $term);
        }

        $total = (int) $conn->fetchOne("SELECT COUNT(*) FROM game WHERE $where", $params);
        $rows = $conn->fetchAllAssociative(
            'SELECT '.self::COLUMNS." FROM game WHERE $where ORDER BY ".self::SORTS[$sort].' LIMIT '.self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE),
            $params,
        );

        return $this->json([
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'items' => array_map($this->present(...), $rows),
        ]);
    }

    /** Fiche de diagnostic : toutes les colonnes de la ligne, telles qu'en base. */
    #[Route('/api/admin/games/{bggId}', name: 'api_admin_game', methods: ['GET'], requirements: ['bggId' => '\d+'])]
    public function show(string $bggId, Connection $conn): JsonResponse
    {
        $row = $conn->fetchAssociative('SELECT * FROM game WHERE bgg_id = ?', [$bggId]);
        if (false === $row) {
            return $this->json(['error' => 'Jeu introuvable.'], 404);
        }

        foreach (['categories', 'mechanics', 'mechanic_families', 'detected_engines', 'expansion_bgg_ids', 'implements_bgg_ids'] as $json) {
            $row[$json] = json_decode((string) $row[$json], true);
        }
        $row['description'] = null === $row['description'] ? null : mb_substr((string) $row['description'], 0, 1500);

        return $this->json($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'bggId' => $row['bgg_id'],
            'name' => $row['name'],
            'nameOriginal' => $row['name_original'],
            'yearPublished' => null === $row['year_published'] ? null : (int) $row['year_published'],
            'bggRank' => null === $row['bgg_rank'] ? null : (int) $row['bgg_rank'],
            'usersRated' => null === $row['users_rated'] ? null : (int) $row['users_rated'],
            'ratingBgg' => null === $row['rating_bgg'] ? null : (float) $row['rating_bgg'],
            'isExpansion' => (bool) $row['is_expansion'],
            'source' => $row['source'],
            'lastSyncedAt' => $this->iso($row['last_synced_at']),
            'lastFetchAttemptAt' => $this->iso($row['last_fetch_attempt_at']),
            'createdAt' => $this->iso($row['created_at']),
            'hasImage' => (bool) $row['has_image'],
            'hasDescription' => (bool) $row['has_description'],
        ];
    }

    private function iso(?string $date): ?string
    {
        return null === $date ? null : (new \DateTimeImmutable($date))->format('c');
    }
}
