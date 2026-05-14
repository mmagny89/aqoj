<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Repository\GameRepository;
use App\Repository\GameSessionRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SessionController extends AbstractController
{
    #[Route('/api/sessions', name: 'api_sessions_list', methods: ['GET'])]
    public function list(
        Request $request,
        GameSessionRepository $repository,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        return $this->json($repository->findByUser($user, 50));
    }

    #[Route('/api/sessions', name: 'api_sessions_create', methods: ['POST'])]
    public function create(
        Request $request,
        GameRepository $gameRepository,
        JwtService $jwt,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
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

        return $this->json($session, 201);
    }
}
