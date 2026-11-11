<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\AssociationGameRepository;
use App\Repository\GameRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserGameRepository;
use App\Service\BggApiService;
use App\Service\MechanicFamilyResolver;
use App\Service\GameEnrichmentService;
use App\Service\RecommendationService;
use App\Translation\DescriptionTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GameController extends AbstractController
{
    use CurrentUser;

    #[Route('/api/library', name: 'api_library', methods: ['GET'])]
    public function library(
        Request $request,
        GameRepository $repository,
        UserGameRepository $userGameRepository,
    ): JsonResponse {
        $user = $this->appUser();
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
    ): JsonResponse {
        $user = $this->appUser();
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
        $results = match ($scope) {
            'discover' => $service->recommendDiscover($user, $criteria),
            'library'  => $service->recommendDiscover($user, $criteria, library: true),
            default    => $service->recommend($user, $criteria),
        };

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
        UserPreferenceRepository $prefRepository,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['recommendations' => [], 'families' => [], 'coldStart' => false, 'declared' => [], 'table' => null]);
        }

        $result = $service->recommendHomeDiscover($user, $request->query->getString('scope') === 'library');

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
            'coldStart' => $result['coldStart'],
            'declared'  => $result['declared'],
            'table'     => $prefRepository->find($user->getId())?->getTable(),
        ]);
    }

    #[Route('/api/games/home-tonight', name: 'api_games_home_tonight', methods: ['GET'])]
    public function homeTonight(
        Request $request,
        RecommendationService $service,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['easy' => [], 'popular' => []]);
        }

        $players = $request->query->getInt('players') ?: null;
        $maxTime = $request->query->getInt('maxTime') ?: null;
        $result  = $service->recommendTonight($user, $players, $maxTime, $request->query->getString('scope') === 'library');

        $serialize = fn(array $list) => array_map(fn($r) => ['game' => $r['game'], 'reason' => $r['reason']], $list);

        return $this->json(['easy' => $serialize($result['easy']), 'popular' => $serialize($result['popular'])]);
    }

    #[Route('/api/games/forgotten', name: 'api_games_forgotten', methods: ['GET'])]
    public function forgotten(
        Request $request,
        RecommendationService $service,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $results = $service->findForgottenGems($user, $request->query->getString('scope') === 'library');

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
        CacheInterface $cache,
        EntityManagerInterface $em,
    ): JsonResponse {
        $query = trim($request->query->getString('q'));
        $players = $request->query->getInt('players') ?: null;
        $maxTime = $request->query->getInt('maxTime') ?: null;
        $category = $request->query->getString('category') ?: null;
        $engines = $request->query->all('engines');
        $page = max(1, $request->query->getInt('page', 1));
        // `local=1` : choisir un jeu dans la base (saisie semi-automatique) ne doit jamais appeler BGG.
        $localOnly = $request->query->getBoolean('local');

        // Utilisateur connecté (optionnel) — pour marquer les jeux possédés
        $user = $this->appUser();
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

            // Fallback BGG si résultats insuffisants. Une même recherche n'interroge BGG qu'une
            // fois par jour (résultat vide compris) ; en cas d'échec, on retente dans 10 minutes.
            if (!$localOnly && count($localResults) < 3 && mb_strlen($query) >= 3) {
                $imported = $cache->get(
                    'bgg_search_' . md5(mb_strtolower($query)),
                    function (ItemInterface $item) use ($query, $repository, $bggService, $enrichment, $em): int {
                        $item->expiresAfter(86400);
                        try {
                            $bggIds = $bggService->searchByQuery($query);
                            $newIds = array_values(array_diff($bggIds, $repository->findExistingBggIds($bggIds)));
                            if ($newIds === []) {
                                return 0;
                            }

                            foreach ($bggService->fetchGamesDetails(array_slice($newIds, 0, 5)) as $gameData) {
                                $em->persist($enrichment->hydrate(new Game(), $gameData));
                            }
                            $em->flush();

                            return count($newIds);
                        } catch (\Throwable) {
                            // BGG indisponible — on retourne les résultats locaux
                            $item->expiresAfter(600);
                            return 0;
                        }
                    },
                );

                if ($imported > 0) {
                    $localResults = $repository->searchByName($query);
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
        GameRepository $repository,
        UserGameRepository $userGameRepository,
        MechanicFamilyResolver $familyResolver,
        GameEnrichmentService $enrichmentService,
        DescriptionTranslator $translator,
        AssociationGameRepository $associationGames,
    ): JsonResponse {
        $game = $repository->find($id);

        if (!$game) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        // Premier enrichissement d'une fiche encore vide (un essai par jour au plus) ; le resync périodique est au cron.
        $enrichmentService->enrichIfNeeded($game);

        $user = $this->appUser();

        // Un membre qui ouvre la fiche d'un jeu non encore traduit déclenche sa traduction (service local, quelques
        // secondes au plus) ; un visiteur anonyme ne le peut pas, pour qu'aucune exploration du catalogue ne sollicite
        // le service. Un échec laisse la description en anglais : le worker reprend le jeu plus tard.
        if ($user !== null && $translator->needsTranslation($game) && $translator->mayAttempt($game)) {
            $translator->translate($game, 8.0);
        }
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

        // Extensions présentes en base
        $expansions      = [];
        $expansionBggIds = $game->getExpansionBggIds();
        if (!empty($expansionBggIds)) {
            $ownedGameIds   = $user ? $userGameRepository->getOwnedGameIds($user) : [];
            $expansionGames = $repository->findByBggIds($expansionBggIds);

            // Les extensions absentes de la base sont importées par le cron (app:bgg:sync-new),
            // jamais pendant la requête d'un joueur.
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

        // Où trouver chaque extension : chez le joueur (`owned`), à l'association ou chez un autre membre.
        $availability = $associationGames->availability(
            array_map(fn(array $g) => (int) $g['id'], [...$expansions, ...$baseGames, ...$siblingExps]),
            $user,
        );
        $withAvailability = fn(array $list): array => array_map(
            fn(array $g) => $g + [
                'inAssociation' => $availability[$g['id']]['association'] ?? false,
                'withMember'    => $availability[$g['id']]['member'] ?? false,
            ],
            $list,
        );
        $expansions  = $withAvailability($expansions);
        $baseGames   = $withAvailability($baseGames);
        $siblingExps = $withAvailability($siblingExps);

        // Recommandation des extensions : celles qu'on a déjà, puis celles qu'on peut emprunter, puis les mieux notées.
        $access = fn(array $g): int => $g['owned'] ? 2 : (($g['inAssociation'] || $g['withMember']) ? 1 : 0);
        usort($expansions, fn($a, $b) => [$access($b), $b['ratingBgg'] ?? 0] <=> [$access($a), $a['ratingBgg'] ?? 0]);

        $data = $game->jsonSerialize();
        $data['descriptionFr']      = $game->hasFreshTranslation() ? $game->getDescriptionFr() : null;
        $data['descriptionFrPending'] = $translator->needsTranslation($game);
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
