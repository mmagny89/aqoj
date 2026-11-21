<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Retour du joueur sur une proposition : « pas intéressé » ou « déjà joué, bof ».
 * Le jeu n'est plus proposé ; « bof » compte aussi comme un signal négatif sur ses mécaniques.
 */
class GameFeedbackController extends AbstractController
{
    use CurrentUser;

    public const VERDICTS = ['not_interested', 'played_meh'];

    #[Route('/api/games/{id}/feedback', name: 'api_game_feedback_put', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function put(int $id, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $data    = json_decode($request->getContent(), true);
        $verdict = is_array($data) ? ($data['verdict'] ?? null) : null;
        if (!is_string($verdict) || !in_array($verdict, self::VERDICTS, true)) {
            return $this->json(['error' => 'Retour inconnu.'], 400);
        }

        if ($connection->fetchOne('SELECT 1 FROM game WHERE id = ?', [$id]) === false) {
            return $this->json(['error' => 'Jeu introuvable'], 404);
        }

        $connection->executeStatement(
            'INSERT INTO game_feedback (user_id, game_id, verdict) VALUES (?, ?, ?)
             ON CONFLICT (user_id, game_id) DO UPDATE SET verdict = EXCLUDED.verdict, created_at = NOW()',
            [$user->getId(), $id, $verdict],
        );

        return $this->json(['gameId' => $id, 'verdict' => $verdict]);
    }

    /** Annule le retour : le jeu peut de nouveau être proposé. Sans effet s'il n'y en avait pas. */
    #[Route('/api/games/{id}/feedback', name: 'api_game_feedback_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, Connection $connection): Response
    {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $connection->executeStatement('DELETE FROM game_feedback WHERE user_id = ? AND game_id = ?', [$user->getId(), $id]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
