<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Repository\GameRepository;
use App\Repository\GameSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SessionController extends AbstractController
{
    #[Route('/api/sessions', name: 'api_sessions_list', methods: ['GET'])]
    public function list(GameSessionRepository $repository): JsonResponse
    {
        return $this->json($repository->findRecentSessions(50));
    }

    #[Route('/api/sessions', name: 'api_sessions_create', methods: ['POST'])]
    public function create(
        Request $request,
        GameRepository $gameRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
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
