<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\BggApiService;
use App\Service\GameEnrichmentService;
use App\Service\JwtService;
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
        GameEnrichmentService $enrichment,
        JwtService $jwt,
        EntityManagerInterface $em,
        GameRepository $gameRepository,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $username = trim($data['username'] ?? $user->getBggUsername() ?? '');

        if ($username === '') {
            return $this->json(['error' => 'Le username BGG est requis'], 400);
        }

        // Sauvegarder le username BGG sur le profil utilisateur
        if ($user->getBggUsername() !== $username) {
            $user->setBggUsername($username);
        }

        try {
            $bggIds = $bggService->fetchCollectionIds($username);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }

        if (empty($bggIds)) {
            $em->flush();
            return $this->json([
                'imported' => 0,
                'total' => 0,
                'skipped' => 0,
                'message' => 'Aucun jeu trouvé pour cet utilisateur BGG.',
            ]);
        }

        // Récupérer les jeux déjà en catalogue
        $existingGames = $gameRepository->findByBggIds($bggIds);
        $existingByBggId = [];
        foreach ($existingGames as $game) {
            $existingByBggId[$game->getBggId()] = $game;
        }

        $newBggIds = array_values(array_diff($bggIds, array_keys($existingByBggId)));
        $imported = 0;

        // Importer les jeux manquants depuis l'API BGG
        foreach (array_chunk($newBggIds, 20) as $chunk) {
            try {
                $gamesData = $bggService->fetchGamesDetails($chunk);
            } catch (\RuntimeException) {
                continue;
            }

            foreach ($gamesData as $gameData) {
                $game = $enrichment->hydrate(new Game(), $gameData);
                $em->persist($game);
                $existingByBggId[$gameData['bggId']] = $game;
                $imported++;
            }

            $em->flush();
        }

        // Lier tous les jeux de la collection BGG à l'utilisateur
        $linked = 0;
        foreach ($bggIds as $bggId) {
            $game = $existingByBggId[$bggId] ?? null;
            if ($game) {
                $user->addGame($game);
                $linked++;
            }
        }

        $em->flush();

        $skipped = count($bggIds) - $imported;

        return $this->json([
            'imported' => $imported,
            'total' => count($bggIds),
            'skipped' => $skipped,
            'bggUsername' => $username,
            'message' => "{$imported} nouveaux jeux ajoutés au catalogue, {$linked} jeux liés à votre collection.",
        ]);
    }
}
