<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\UserGameRepository;
use App\Service\BggApiService;
use App\Service\MechanicFamilyResolver;
use App\Service\GameEnrichmentService;
use App\Service\JwtService;
use App\Service\RecommendationService;
// Note : BggApiService et GameEnrichmentService restent utilisés dans search()
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/api/library', name: 'api_library', methods: ['GET'])]
    public function library(
        Request $request,
        GameRepository $repository,
        UserGameRepository $userGameRepository,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $page = max(1, $request->query->getInt('page', 1));

        // played=1 → notés (joués), played=0 → non notés, absent → tous
        $playedParam = $request->query->has('played')
            ? ($request->query->getBoolean('played') ? true : false)
            : null;

        // isExpansion=1 → extensions seulement, isExpansion=0 → jeux de base seulement, absent → tous
        $isExpansionParam = $request->query->has('isExpansion')
            ? ($request->query->getBoolean('isExpansion') ? true : false)
            : null;

        $games       = $repository->findByUserCollection($user, $page, 50, $playedParam, $isExpansionParam);
        $total       = $repository->countUserCollection($user, $playedParam, $isExpansionParam);
        $userRatings = $userGameRepository->getBggUserRatings($user);

        return $this->json([
            'games'       => $games,
            'bggUsername' => $user->getBggUsername(),
            'total'       => $total,
            'hasMore'     => count($games) === 50,
            'userRatings' => $userRatings,
        ]);
    }

    #[Route('/api/games', name: 'api_games_list', methods: ['GET'])]
    public function list(GameRepository $repository): JsonResponse
    {
        return $this->json($repository->findByCriteria(null, null));
    }

    #[Route('/api/games/recommendation', name: 'api_games_recommendation', methods: ['GET'])]
    public function recommendation(
        Request $request,
        RecommendationService $service,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise pour les recommandations.'], 401);
        }

        $criteria = [
            'players'    => $request->query->getInt('players') ?: null,
            'maxTime'    => $request->query->getInt('maxTime') ?: null,
            'families'   => $request->query->all('families'),
            'categories' => $request->query->all('categories'),
        ];

        $scope   = $request->query->getString('scope', 'collection');
        $results = $scope === 'discover'
            ? $service->recommendDiscover($user, $criteria)
            : $service->recommend($user, $criteria);

        return $this->json(array_map(fn($r) => [
            'game'   => $r['game'],
            'score'  => round($r['score'], 2),
            'reason' => $r['reason'],
        ], $results));
    }

    #[Route('/api/games/home-reco', name: 'api_games_home_reco', methods: ['GET'])]
    public function homeRecommendations(
        Request $request,
        RecommendationService $service,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['recommendations' => [], 'families' => []]);
        }

        $result = $service->recommendHomeDiscover($user);

        $serialize = fn(array $list) => array_map(
            fn($r) => ['game' => $r['game'], 'reason' => $r['reason']],
            $list
        );

        $byDecade = [];
        foreach ($result['byDecade'] as $label => $list) {
            $byDecade[$label] = $serialize($list);
        }

        return $this->json([
            'families' => $result['families'],
            'recent'   => $serialize($result['recent']),
            'byDecade' => $byDecade,
        ]);
    }

    #[Route('/api/games/forgotten', name: 'api_games_forgotten', methods: ['GET'])]
    public function forgotten(
        Request $request,
        RecommendationService $service,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $results = $service->findForgottenGems($user);

        return $this->json(array_map(fn($r) => [
            'game'   => $r['game'],
            'reason' => $r['reason'],
        ], $results));
    }

    #[Route('/api/games/search', name: 'api_games_search', methods: ['GET'])]
    public function search(
        Request $request,
        GameRepository $repository,
        UserGameRepository $userGameRepository,
        BggApiService $bggService,
        GameEnrichmentService $enrichment,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $query = trim($request->query->getString('q'));
        $players = $request->query->getInt('players') ?: null;
        $maxTime = $request->query->getInt('maxTime') ?: null;
        $category = $request->query->getString('category') ?: null;
        $engines = $request->query->all('engines');
        $page = max(1, $request->query->getInt('page', 1));

        // Utilisateur connecté (optionnel) — pour marquer les jeux possédés
        $user = AuthController::extractUser($request, $jwt, $em);
        $ownedIds = $user ? $userGameRepository->getOwnedGameIds($user) : [];

        /** @param Game[] $games */
        $withOwned = function (array $games) use ($ownedIds): array {
            return array_map(function (Game $g) use ($ownedIds): array {
                $data = $g->jsonSerialize();
                $data['owned'] = isset($ownedIds[$g->getId()]);
                return $data;
            }, $games);
        };

        // Recherche textuelle
        if ($query !== '') {
            $localResults = $repository->searchByName($query);

            // Fallback BGG si résultats insuffisants
            if (count($localResults) < 3) {
                try {
                    $bggIds = $bggService->searchByQuery($query);

                    if (!empty($bggIds)) {
                        $existingIds = $repository->findExistingBggIds($bggIds);
                        $newIds = array_values(array_diff($bggIds, $existingIds));

                        if (!empty($newIds)) {
                            $gamesData = $bggService->fetchGamesDetails(array_slice($newIds, 0, 5));

                            foreach ($gamesData as $gameData) {
                                $game = $enrichment->hydrate(new Game(), $gameData);
                                $em->persist($game);
                            }

                            $em->flush();
                            $localResults = $repository->searchByName($query);
                        }
                    }
                } catch (\Throwable) {
                    // BGG indisponible — on retourne les résultats locaux
                }
            }

            return $this->json($withOwned($localResults));
        }

        // Recherche par filtres (sans texte) — moteurs Engelstein prioritaires
        if (!empty($engines)) {
            $games = $repository->findByEngines($engines, $players, $maxTime, $page);
        } else {
            $games = $repository->findByCriteria(
                $players,
                $maxTime,
                $category ? [$category] : [],
                [],
                $page,
            );
        }

        return $this->json($withOwned($games));
    }

    #[Route('/api/games/{id}', name: 'api_game_detail', methods: ['GET'])]
    public function detail(
        int $id,
        Request $request,
        GameRepository $repository,
        UserGameRepository $userGameRepository,
        MechanicFamilyResolver $familyResolver,
        GameEnrichmentService $enrichmentService,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $game = $repository->find($id);

        if (!$game) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        // Re-synchroniser si données potentiellement périmées (enrichissement initial ou resync mensuel).
        $enrichmentService->enrichIfNeeded($game);

        $user = AuthController::extractUser($request, $jwt, $em);
        $bggUserRating = null;
        if ($user) {
            $ratings = $userGameRepository->getBggUserRatings($user);
            $bggUserRating = $ratings[$game->getId()] ?? null;
        }

        // Grouper les mécaniques BGG par famille Engelstein pour l'affichage
        $familyMap = $familyResolver->getFamilyMap();
        $mechanicsByFamily = [];
        foreach ($game->getMechanics() as $mechanic) {
            $family = $familyMap[$mechanic] ?? null;
            if ($family !== null) {
                $mechanicsByFamily[$family][] = $mechanic;
            }
        }

        // Familles dominantes = moteurs détectés si présents,
        // sinon les 2 familles de support avec le plus de mécaniques matchées
        $displayEngines = $game->getDisplayEngines();
        if (!empty($displayEngines)) {
            $primaryFamilies = array_map(fn($e) => ['family' => $e, 'isEngine' => true], $displayEngines);
        } else {
            $countsByFamily = array_map('count', $mechanicsByFamily);
            arsort($countsByFamily);
            $top = array_slice(array_keys($countsByFamily), 0, 2);
            $primaryFamilies = array_map(fn($f) => ['family' => $f, 'isEngine' => false], $top);
        }

        // Extensions : chercher en base, puis importer les manquantes depuis BGG (max 20 à la fois)
        $expansions      = [];
        $expansionBggIds = $game->getExpansionBggIds();
        if (!empty($expansionBggIds)) {
            $ownedGameIds   = $user ? $userGameRepository->getOwnedGameIds($user) : [];
            $expansionGames = $repository->findByBggIds($expansionBggIds);

            // Déterminer les BGG IDs manquants en base
            $foundBggIds  = array_map(fn(Game $g) => $g->getBggId(), $expansionGames);
            $missingBggIds = array_values(array_diff($expansionBggIds, $foundBggIds));

            // Importer les manquants depuis BGG (limité à 20 pour ne pas bloquer la requête)
            if (!empty($missingBggIds)) {
                try {
                    $bggApi = $enrichmentService->getBggApiService();
                    $batch  = array_slice($missingBggIds, 0, 20);
                    $details = $bggApi->fetchGamesDetails($batch);
                    foreach ($details as $data) {
                        $existing = $repository->findOneBy(['bggId' => $data['bggId']]);
                        if (!$existing) {
                            $newGame = new Game();
                            $newGame->setIsExpansion(true);
                            $enrichmentService->hydrate($newGame, $data);
                            $em->persist($newGame);
                            $expansionGames[] = $newGame;
                        }
                    }
                    $em->flush();
                } catch (\Throwable) {
                    // Si BGG est indisponible, on continue sans les extensions manquantes
                }
            }

            foreach ($expansionGames as $exp) {
                $expData          = $exp->jsonSerialize();
                $expData['owned'] = isset($ownedGameIds[$exp->getId()]);
                $expansions[]     = $expData;
            }

            // Trier : possédées en premier, puis par rang BGG
            usort($expansions, function ($a, $b) {
                if ($a['owned'] !== $b['owned']) {
                    return $b['owned'] <=> $a['owned'];
                }
                return ($a['bggRank'] ?? PHP_INT_MAX) <=> ($b['bggRank'] ?? PHP_INT_MAX);
            });
        }

        // Jeu de base + extensions sœurs (quand le jeu consulté est lui-même une extension)
        $baseGames   = [];
        $siblingExps = [];
        if ($game->isExpansion() && !empty($game->getImplementsBggIds())) {
            $ownedGameIds ??= $user ? $userGameRepository->getOwnedGameIds($user) : [];
            $baseGameEntities = $repository->findByBggIds($game->getImplementsBggIds());

            foreach ($baseGameEntities as $baseGame) {
                $baseData          = $baseGame->jsonSerialize();
                $baseData['owned'] = isset($ownedGameIds[$baseGame->getId()]);
                $baseGames[]       = $baseData;

                // Extensions sœurs = toutes les extensions du jeu de base, sauf le jeu courant
                $siblingBggIds = array_filter(
                    $baseGame->getExpansionBggIds(),
                    fn($bid) => $bid !== $game->getBggId()
                );
                if (!empty($siblingBggIds)) {
                    $siblings = $repository->findByBggIds(array_values($siblingBggIds));
                    foreach ($siblings as $sib) {
                        $sibData          = $sib->jsonSerialize();
                        $sibData['owned'] = isset($ownedGameIds[$sib->getId()]);
                        $siblingExps[]    = $sibData;
                    }
                }
            }

            // Trier les extensions sœurs : possédées en premier, puis alphabétique
            usort($siblingExps, function ($a, $b) {
                if ($a['owned'] !== $b['owned']) {
                    return $b['owned'] <=> $a['owned'];
                }
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });
        }

        $data = $game->jsonSerialize();
        $data['bggUserRating']    = $bggUserRating;
        $data['mechanicsByFamily'] = $mechanicsByFamily;
        $data['primaryFamilies']  = $primaryFamilies;
        $data['expansions']       = $expansions;
        $data['baseGames']        = $baseGames;
        $data['siblingExpansions'] = $siblingExps;

        return $this->json($data);
    }

    #[Route('/api/games/{id}/similar', name: 'api_game_similar', methods: ['GET'])]
    public function similar(
        int $id,
        Request $request,
        GameRepository $repository,
        RecommendationService $recommendationService,
    ): JsonResponse {
        $game = $repository->find($id);
        if (!$game) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        $limit   = max(5, min(50, (int) ($request->query->get('limit', 5))));
        $results = $recommendationService->findSimilar($game, $limit);

        return $this->json(array_map(fn($r) => [
            ...$r['game']->jsonSerialize(),
            'similarity' => $r['similarity'],
            'reason'     => $r['reason'],
        ], $results));
    }

}
