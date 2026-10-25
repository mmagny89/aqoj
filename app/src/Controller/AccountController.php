<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AccountMailer;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
                'SELECT top_engines AS "topEngines", top_support AS "topSupport", ranked_families AS "rankedFamilies", top_categories AS "topCategories",
                        declared_families AS "declaredFamilies", table_players AS "tablePlayers", table_max_time AS "tableMaxTime"
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

    /**
     * Changement de mot de passe : exige l'actuel (une session volée ne suffit pas à prendre le compte) et
     * révoque les autres sessions ; le jeton rendu garde celle-ci ouverte.
     */
    #[Route('/api/user/password', name: 'api_user_password', methods: ['PATCH'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        AccountMailer $mailer,
        RateLimiterFactoryInterface $loginLimiter,
    ): JsonResponse {
        $user = $this->currentUser();

        $limit = $loginLimiter->create('mdp-'.$user->getId())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        $current = (string) ($data['currentPassword'] ?? '');
        $new = (string) ($data['newPassword'] ?? '');

        if (!password_verify($current, $user->getPasswordHash())) {
            return $this->json(['error' => 'Mot de passe actuel incorrect.'], 403);
        }
        if (strlen($new) < 8) {
            return $this->json(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], 422);
        }
        if ($new === $current) {
            return $this->json(['error' => "Le nouveau mot de passe doit différer de l'actuel."], 422);
        }

        $user->changePasswordHash(password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]));
        $em->flush();
        try {
            $mailer->sendPasswordChanged($user);
        } catch (\Throwable) {
        }

        return $this->json(['token' => $jwt->generateToken($user), 'user' => $user]);
    }

    /** Numéro d'adhérent à l'association (EDJ-0001) : saisi par la personne, non vérifié auprès de l'association. */
    #[Route('/api/user/member-number', name: 'api_user_member_number', methods: ['PATCH'])]
    public function memberNumber(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentUser();

        $data = json_decode($request->getContent(), true);
        $raw = trim((string) (is_array($data) ? ($data['memberNumber'] ?? '') : ''));

        $number = null;
        if ($raw !== '') {
            $number = self::normalizeMemberNumber($raw);
            if ($number === null) {
                return $this->json(['error' => "Numéro d'adhérent illisible : il ressemble à EDJ-0123."], 422);
            }
        }

        $user->setMemberNumber($number);
        $em->flush();

        return $this->json($user);
    }

    /** « edj 123 », « EDJ-0123 » ou « 123 » donnent EDJ-0123 ; tout le reste est refusé. */
    public static function normalizeMemberNumber(string $raw): ?string
    {
        $compact = strtoupper((string) preg_replace('/[\s\-]+/', '', $raw));

        return preg_match('/^(?:EDJ)?(\d{1,12})$/', $compact, $m) === 1
            ? 'EDJ-'.str_pad($m[1], 4, '0', STR_PAD_LEFT)
            : null;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
