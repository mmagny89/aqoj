<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\BggApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BggController extends AbstractController
{
    #[Route('/api/bgg/import', name: 'api_bgg_import', methods: ['POST'])]
    public function import(
        Request $request,
        BggApiService $bggService,
        EntityManagerInterface $em,
        GameRepository $gameRepository,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = trim($data['username'] ?? '');

        if ($username === '') {
            return $this->json(['error' => 'Le username BGG est requis'], 400);
        }

        try {
            $bggIds = $bggService->fetchCollectionIds($username);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }

        if (empty($bggIds)) {
            return $this->json([
                'imported' => 0,
                'total' => 0,
                'skipped' => 0,
                'message' => 'Aucun jeu trouvé pour cet utilisateur BGG.',
            ]);
        }

        $existingIds = $gameRepository->findExistingBggIds($bggIds);
        $newIds = array_values(array_diff($bggIds, $existingIds));

        $imported = 0;
        $chunks = array_chunk($newIds, 20);

        foreach ($chunks as $chunk) {
            try {
                $gamesData = $bggService->fetchGamesDetails($chunk);
            } catch (\RuntimeException) {
                continue;
            }

            foreach ($gamesData as $gameData) {
                $game = (new Game())
                    ->setBggId($gameData['bggId'])
                    ->setName($gameData['name'])
                    ->setDescription($gameData['description'])
                    ->setMinPlayers($gameData['minPlayers'])
                    ->setMaxPlayers($gameData['maxPlayers'])
                    ->setPlayingTime($gameData['playingTime'])
                    ->setComplexity($gameData['complexity'])
                    ->setCategories($gameData['categories'])
                    ->setMechanics($gameData['mechanics'])
                    ->setImageUrl($gameData['imageUrl'])
                    ->setYearPublished($gameData['yearPublished'])
                    ->setRatingBgg($gameData['ratingBgg']);

                $em->persist($game);
                $imported++;
            }

            $em->flush();
        }

        return $this->json([
            'imported' => $imported,
            'total' => count($bggIds),
            'skipped' => count($existingIds),
            'message' => "{$imported} nouveaux jeux importés sur " . count($bggIds) . " dans la collection.",
        ]);
    }
}
