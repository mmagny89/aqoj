<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\BggApiService;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/api/games', name: 'api_games_list', methods: ['GET'])]
    public function list(GameRepository $repository): JsonResponse
    {
        return $this->json($repository->findAll());
    }

    #[Route('/api/games/recommendation', name: 'api_games_recommendation', methods: ['GET'])]
    public function recommendation(Request $request, RecommendationService $service): JsonResponse
    {
        $criteria = [
            'players' => $request->query->getInt('players') ?: null,
            'maxTime' => $request->query->getInt('maxTime') ?: null,
            'categories' => $request->query->all('categories'),
            'mechanics' => $request->query->all('mechanics'),
        ];

        $results = $service->recommend($criteria);

        return $this->json(array_map(fn($r) => [
            'game' => $r['game'],
            'score' => round($r['score'], 2),
            'reason' => $r['reason'],
        ], $results));
    }

    #[Route('/api/games/forgotten', name: 'api_games_forgotten', methods: ['GET'])]
    public function forgotten(RecommendationService $service): JsonResponse
    {
        $results = $service->findForgottenGems();

        return $this->json(array_map(fn($r) => [
            'game' => $r['game'],
            'reason' => $r['reason'],
        ], $results));
    }

    #[Route('/api/games/search', name: 'api_games_search', methods: ['GET'])]
    public function search(
        Request $request,
        GameRepository $repository,
        BggApiService $bggService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $query = trim($request->query->getString('q'));
        $players = $request->query->getInt('players') ?: null;
        $maxTime = $request->query->getInt('maxTime') ?: null;
        $category = $request->query->getString('category') ?: null;

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
                                $game = $this->hydrateGame(new Game(), $gameData);
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

            return $this->json($localResults);
        }

        // Recherche par filtres (sans texte)
        $games = $repository->findByCriteria(
            $players,
            $maxTime,
            $category ? [$category] : [],
            [],
        );

        return $this->json($games);
    }

    #[Route('/api/games/{id}', name: 'api_game_detail', methods: ['GET'])]
    public function detail(int $id, GameRepository $repository): JsonResponse
    {
        $game = $repository->find($id);

        if (!$game) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        return $this->json($game);
    }

    private function hydrateGame(Game $game, array $data): Game
    {
        return $game
            ->setBggId($data['bggId'])
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setMinPlayers($data['minPlayers'])
            ->setMaxPlayers($data['maxPlayers'])
            ->setPlayingTime($data['playingTime'])
            ->setComplexity($data['complexity'])
            ->setCategories($data['categories'])
            ->setMechanics($data['mechanics'])
            ->setImageUrl($data['imageUrl'])
            ->setYearPublished($data['yearPublished'])
            ->setRatingBgg($data['ratingBgg'])
            ->setLastSyncedAt(new \DateTimeImmutable());
    }
}
