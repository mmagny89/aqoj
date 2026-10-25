<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Repository\GameRepository;
use App\Repository\GameSessionRepository;
use App\Service\UserPreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SessionController extends AbstractController
{
    use CurrentUser;

    #[Route('/api/sessions', name: 'api_sessions_list', methods: ['GET'])]
    public function list(
        Request $request,
        GameSessionRepository $repository,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $limit  = max(1, min(200, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset'));

        return $this->json($repository->findByUser($user, $limit, $offset));
    }

    /** Le bilan de toutes les parties (nombre, jeux différents, aimées), indépendant de la page affichée. */
    #[Route('/api/sessions/summary', name: 'api_sessions_summary', methods: ['GET'])]
    public function summary(GameSessionRepository $repository): JsonResponse
    {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        return $this->json($repository->summaryForUser($user));
    }

    #[Route('/api/sessions', name: 'api_sessions_create', methods: ['POST'])]
    public function create(
        Request $request,
        GameRepository $gameRepository,
        EntityManagerInterface $em,
        UserPreferenceService $prefService,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $gameId = $data['gameId'] ?? null;
        $playersCount = $data['playersCount'] ?? null;

        if (!$gameId || !$playersCount) {
            return $this->json(['error' => 'gameId et playersCount sont requis'], 400);
        }

        $game = $gameRepository->find($gameId);
        if (!$game) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        $session = (new GameSession())
            ->setUser($user)
            ->setGame($game)
            ->setPlayersCount((int) $playersCount)
            ->setRating(isset($data['rating']) ? (int) $data['rating'] : null)
            ->setNote($data['note'] ?? null)
            ->setContext($data['context'] ?? null);

        if (isset($data['playedAt'])) {
            try {
                $session->setPlayedAt(new \DateTimeImmutable($data['playedAt']));
            } catch (\Exception) {
                // use default (now)
            }
        }

        $em->persist($session);
        $em->flush();

        // Recalculer les préférences si la partie est aimée (signal pertinent)
        if ($session->getRating() === 5) {
            try { $prefService->recompute($user); } catch (\Throwable) { /* non bloquant */ }
        }

        return $this->json($session, 201);
    }
}
