<?php

namespace App\Controller;

use App\MyLudo\CsvParser;
use App\MyLudo\Matcher;
use App\Repository\AssociationGameRepository;
use App\Repository\UserGameRepository;
use App\Service\UserPreferenceService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Import de la ludothèque depuis l'export CSV de MyLudo, en deux temps : le fichier est d'abord analysé
 * (rien n'est écrit), puis le joueur confirme les rapprochements et seuls ceux-là entrent dans sa ludothèque.
 */
class MyLudoController extends AbstractController
{
    use CurrentUser;

    private const MAX_CSV_BYTES = 3_000_000;
    private const MAX_ROWS = 3000;

    #[Route('/api/myludo/analyze', name: 'api_myludo_analyze', methods: ['POST'])]
    public function analyze(Request $request, CsvParser $parser, Matcher $matcher): JsonResponse
    {
        $csv = json_decode($request->getContent(), true)['csv'] ?? null;
        if (!is_string($csv) || trim($csv) === '') {
            return $this->json(['error' => 'Déposez le fichier CSV exporté de MyLudo.'], 400);
        }
        if (strlen($csv) > self::MAX_CSV_BYTES) {
            return $this->json(['error' => 'Fichier trop volumineux (3 Mo au plus).'], 413);
        }

        try {
            $rows = $parser->parse($csv);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
        if ($rows === []) {
            return $this->json(['error' => 'Aucun jeu dans ce fichier.'], 422);
        }
        if (count($rows) > self::MAX_ROWS) {
            return $this->json(['error' => 'Trop de jeux (' . self::MAX_ROWS . ' au plus).'], 413);
        }

        $matched = $matcher->match($rows);
        $count   = fn(string $status) => count(array_filter($matched, fn(array $r) => $r['status'] === $status));

        return $this->json([
            'rows'    => $matched,
            'summary' => [
                'total'     => count($matched),
                'matched'   => $count('matched'),
                'ambiguous' => $count('ambiguous'),
                'unmatched' => $count('unmatched'),
            ],
        ]);
    }

    /** Ajoute à la ludothèque du joueur les jeux confirmés : `{items: [{gameId, rating?}]}`. */
    #[Route('/api/myludo/import', name: 'api_myludo_import', methods: ['POST'])]
    public function import(
        Request $request,
        Connection $conn,
        UserGameRepository $userGames,
        AssociationGameRepository $associationGames,
        UserPreferenceService $prefService,
    ): JsonResponse {
        $user   = $this->appUser();
        $body   = json_decode($request->getContent(), true);
        $items  = $body['items'] ?? null;
        $target = $body['target'] ?? 'me';
        if (!in_array($target, ['me', 'association'], true)) {
            return $this->json(['error' => 'Destination inconnue.'], 400);
        }
        // La ludothèque de l'association ne se renseigne que par un administrateur.
        if ($target === 'association' && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Réservé aux administrateurs.'], 403);
        }
        if (!is_array($items) || !array_is_list($items) || $items === []) {
            return $this->json(['error' => 'Aucun jeu à importer.'], 400);
        }
        if (count($items) > self::MAX_ROWS) {
            return $this->json(['error' => 'Trop de jeux (' . self::MAX_ROWS . ' au plus).'], 413);
        }

        $ratings = [];
        foreach ($items as $item) {
            $gameId = is_array($item) ? ($item['gameId'] ?? null) : null;
            if (!is_int($gameId) || $gameId < 1) {
                return $this->json(['error' => 'Jeu invalide.'], 400);
            }
            $rating = $item['rating'] ?? null;
            $rating = is_int($rating) || is_float($rating) ? (float) $rating : null;
            $ratings[$gameId] = $rating !== null && $rating > 0 && $rating <= 10 ? $rating : ($ratings[$gameId] ?? null);
        }

        // Seuls des jeux du catalogue entrent : un identifiant inventé ne doit pas créer de ligne.
        $known = array_map('intval', $conn->fetchFirstColumn(
            'SELECT id FROM game WHERE id IN (' . implode(',', array_fill(0, count($ratings), '?')) . ')',
            array_keys($ratings),
        ));
        if (count($known) !== count($ratings)) {
            return $this->json(['error' => 'Un des jeux n\'existe pas dans le catalogue.'], 422);
        }

        if ($target === 'association') {
            // Les notes personnelles sont celles d'un joueur : elles n'ont pas de sens pour la ludothèque commune.
            $result = $associationGames->addMany(array_keys($ratings), $user);
            $rated  = 0;
        } else {
            $result = $userGames->addMany($user, $ratings);
            $prefService->recompute($user);
            $rated = count(array_filter($ratings, fn($r) => $r !== null));
        }

        return $this->json([
            'total'    => count($ratings),
            'added'    => $result['added'],
            'existing' => $result['existing'],
            'rated'    => $rated,
            'target'   => $target,
        ]);
    }
}
