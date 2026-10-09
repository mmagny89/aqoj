<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Droits d'accès, de portabilité et d'effacement (RGPD) sur le compte du joueur connecté.
 * Protégé par `access_control` (`^/api/user`).
 */
class AccountController extends AbstractController
{
    #[Route('/api/user/export', name: 'api_user_export', methods: ['GET'])]
    public function export(Connection $connection): JsonResponse
    {
        $user = $this->currentUser();
        $id = $user->getId();

        $response = $this->json([
            'exportedAt' => (new \DateTimeImmutable())->format('c'),
            'account' => $user,
            'collection' => $connection->fetchAllAssociative(
                'SELECT g.bgg_id AS "bggId", g.name, ug.bgg_user_rating AS "rating"
                 FROM user_game ug JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ? ORDER BY g.name',
                [$id],
            ),
            'sessions' => $connection->fetchAllAssociative(
                'SELECT g.bgg_id AS "bggId", g.name, s.players_count AS "playersCount", s.rating, s.note, s.played_at AS "playedAt", s.context
                 FROM game_session s JOIN game g ON g.id = s.game_id
                 WHERE s.user_id = ? ORDER BY s.played_at DESC',
                [$id],
            ),
            'preferences' => $connection->fetchAssociative(
                'SELECT top_engines AS "topEngines", top_support AS "topSupport", ranked_families AS "rankedFamilies", top_categories AS "topCategories"
                 FROM user_preference WHERE user_id = ?',
                [$id],
            ) ?: null,
        ]);
        $response->headers->set('Content-Disposition', 'attachment; filename="aqoj-mes-donnees.json"');

        return $response;
    }

    #[Route('/api/user/account', name: 'api_user_account_delete', methods: ['DELETE'])]
    public function delete(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->currentUser();

        $data = json_decode($request->getContent(), true);
        $password = is_array($data) ? (string) ($data['password'] ?? '') : '';
        if (!password_verify($password, $user->getPasswordHash())) {
            return $this->json(['error' => 'Mot de passe incorrect.'], 403);
        }

        // Les parties jouées sont effacées avec le compte : la clé étrangère les conserverait sans propriétaire.
        // user_game et user_preference partent en cascade.
        $connection->transactional(function (Connection $tx) use ($user): void {
            $tx->executeStatement('DELETE FROM game_session WHERE user_id = ?', [$user->getId()]);
            $tx->executeStatement('DELETE FROM app_user WHERE id = ?', [$user->getId()]);
        });

        return $this->json(['deleted' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
