<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GameRepository;
use App\Service\RecommendationService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Retour du joueur sur une proposition :
 *   - `not_interested`, `played_meh` : le jeu n'est plus proposé ; « bof » pèse aussi contre ses mécaniques ;
 *   - `played_liked` : le jeu reste proposé, et ses mécaniques comptent parmi les goûts ;
 *   - `want_to_play` : le jeu reste proposé, et ses mécaniques comptent parmi les goûts.
 * Un retour `source = 'auto'` est déduit en base des parties et des notes (`game_feedback_sync_auto`) : il coche le bouton
 * mais n'écarte rien ; le choix du joueur (`source = 'user'`) l'emporte toujours.
 */
class GameFeedbackController extends AbstractController
{
    use CurrentUser;

    public const VERDICTS = ['not_interested', 'played_meh', 'want_to_play', 'played_liked'];

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
            "INSERT INTO game_feedback (user_id, game_id, verdict) VALUES (?, ?, ?)
             ON CONFLICT (user_id, game_id) DO UPDATE SET verdict = EXCLUDED.verdict, created_at = NOW(), source = 'user'",
            [$user->getId(), $id, $verdict],
        );

        return $this->json(['gameId' => $id, 'verdict' => $verdict]);
    }

    /** Les jeux que le joueur aimerait jouer, le dernier ajouté d'abord. Une partie enregistrée sur un jeu le retire (déclencheur SQL). */
    #[Route('/api/user/wishlist', name: 'api_user_wishlist', methods: ['GET'])]
    public function wishlist(Connection $connection, GameRepository $games, RecommendationService $recommendations): JsonResponse
    {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $ids = array_map('intval', $connection->fetchFirstColumn(
            "SELECT game_id FROM game_feedback WHERE user_id = ? AND verdict = 'want_to_play' ORDER BY created_at DESC, game_id LIMIT 60",
            [$user->getId()],
        ));
        if ($ids === []) {
            return $this->json([]);
        }

        $byId = [];
        foreach ($games->findBy(['id' => $ids]) as $game) {
            $byId[$game->getId()] = $game;
        }

        $why = $recommendations->explainer($user);
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = ['game' => $byId[$id], 'reason' => '', 'why' => $why($byId[$id]), 'feedback' => 'want_to_play'];
            }
        }

        return $this->json($out);
    }

    /**
     * Tous les votes du joueur (dernier d'abord), pour les revoir, les changer ou les annuler depuis « Mon compte ».
     * `source` dit si le vote vient d'un clic (`user`) ou des parties et des notes (`auto`).
     */
    #[Route('/api/user/feedback', name: 'api_user_feedback', methods: ['GET'])]
    public function history(Connection $connection, GameRepository $games): JsonResponse
    {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT game_id, verdict, source, created_at FROM game_feedback WHERE user_id = ? ORDER BY created_at DESC, game_id LIMIT 500',
            [$user->getId()],
        );
        if ($rows === []) {
            return $this->json([]);
        }

        $byId = [];
        foreach ($games->findBy(['id' => array_map(static fn(array $r): int => (int) $r['game_id'], $rows)]) as $game) {
            $byId[$game->getId()] = $game;
        }

        $out = [];
        foreach ($rows as $row) {
            $game = $byId[(int) $row['game_id']] ?? null;
            if ($game) {
                $out[] = ['game' => $game, 'verdict' => $row['verdict'], 'source' => $row['source'], 'votedAt' => $row['created_at']];
            }
        }

        return $this->json($out);
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
