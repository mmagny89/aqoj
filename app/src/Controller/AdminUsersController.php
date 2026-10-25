<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Comptes : liste et changement de rôle (Membre, VIP). Le statut d'administrateur ne se donne
 * pas ici : il passe par `app:user:admin`, une commande console.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminUsersController extends AbstractController
{
    private const PER_PAGE = 50;

    #[Route('/api/admin/users', name: 'api_admin_users', methods: ['GET'])]
    public function list(Request $request, Connection $conn): JsonResponse
    {
        $role = $request->query->getString('role');
        $term = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));

        $where = ['TRUE'];
        $params = [];
        if ($role !== '') {
            if (UserRole::tryFrom($role) === null && $role !== 'admin') {
                return $this->json(['error' => 'Rôle inconnu.'], 400);
            }
            $where[] = $role === 'admin' ? 'u.is_admin = TRUE' : 'u.role = ?';
            if ($role !== 'admin') {
                $params[] = $role;
            }
        }
        if ($term !== '') {
            $where[] = '(u.email ILIKE ? OR u.member_number ILIKE ?)';
            $like = '%'.addcslashes($term, '%_\\').'%';
            array_push($params, $like, $like);
        }
        $clause = implode(' AND ', $where);

        $total = (int) $conn->fetchOne("SELECT COUNT(*) FROM app_user u WHERE $clause", $params);
        $rows = $conn->fetchAllAssociative(
            "SELECT u.id, u.email, u.role, u.is_admin, u.bgg_username, u.member_number, u.email_verified_at, u.created_at, i.label AS invitation_label
             FROM app_user u LEFT JOIN invitation_code i ON i.id = u.invitation_code_id
             WHERE $clause ORDER BY u.created_at DESC, u.id DESC LIMIT ".self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE),
            $params,
        );

        return $this->json([
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'items' => array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'email' => $r['email'],
                'role' => $r['role'],
                'isAdmin' => (bool) $r['is_admin'],
                'bggUsername' => $r['bgg_username'],
                'memberNumber' => $r['member_number'],
                'emailVerified' => $r['email_verified_at'] !== null,
                'createdAt' => (new \DateTimeImmutable($r['created_at']))->format('c'),
                'invitationLabel' => $r['invitation_label'],
            ], $rows),
        ]);
    }

    #[Route('/api/admin/users/{id}', name: 'api_admin_user_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, UserRepository $users, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $users->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Compte introuvable.'], 404);
        }
        if ($security->getUser() === $user) {
            return $this->json(['error' => 'Vous ne pouvez pas changer votre propre rôle.'], 422);
        }

        $data = json_decode($request->getContent(), true);
        $role = UserRole::tryFrom((string) (\is_array($data) ? ($data['role'] ?? '') : ''));
        if ($role === null) {
            return $this->json(['error' => 'Rôle inconnu : member ou vip.'], 422);
        }

        $user->setRole($role);
        $em->flush();

        return $this->json($user);
    }
}
