<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InvitationCode;
use App\Enum\UserRole;
use App\Repository\InvitationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Codes d'invitation : l'inscription est réservée à qui en détient un (REGISTRATION_REQUIRES_CODE). */
#[IsGranted('ROLE_ADMIN')]
final class AdminInvitationsController extends AbstractController
{
    #[Route('/api/admin/invitations', name: 'api_admin_invitations', methods: ['GET'])]
    public function list(InvitationCodeRepository $invitations): JsonResponse
    {
        return $this->json(array_map($this->present(...), $invitations->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], 200)));
    }

    #[Route('/api/admin/invitations', name: 'api_admin_invitation_create', methods: ['POST'])]
    public function create(Request $request, InvitationCodeRepository $invitations, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = \is_array($data) ? $data : [];

        $label = trim((string) ($data['label'] ?? ''));
        $role = UserRole::tryFrom((string) ($data['role'] ?? 'member'));
        $maxUses = (int) ($data['maxUses'] ?? 1);
        if ($label === '' || mb_strlen($label) > 100) {
            return $this->json(['error' => 'Le libellé est obligatoire (100 caractères au plus).'], 422);
        }
        if ($role === null) {
            return $this->json(['error' => 'Rôle inconnu.'], 422);
        }
        if ($maxUses < 1 || $maxUses > 1000) {
            return $this->json(['error' => "Le nombre d'usages doit être compris entre 1 et 1000."], 422);
        }

        $expiresAt = null;
        if (!empty($data['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable((string) $data['expiresAt']);
            } catch (\Exception) {
                return $this->json(['error' => 'Date limite illisible.'], 422);
            }
            if ($expiresAt <= new \DateTimeImmutable()) {
                return $this->json(['error' => 'La date limite doit être dans le futur.'], 422);
            }
        }

        $code = new InvitationCode($invitations->generateCode(), $label, $role, $maxUses, $expiresAt, $security->getUser() instanceof \App\Entity\User ? $security->getUser() : null);
        $em->persist($code);
        $em->flush();

        return $this->json($this->present($code), 201);
    }

    #[Route('/api/admin/invitations/{id}/revoke', name: 'api_admin_invitation_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(int $id, InvitationCodeRepository $invitations, EntityManagerInterface $em): JsonResponse
    {
        $code = $invitations->find($id);
        if ($code === null) {
            return $this->json(['error' => 'Code introuvable.'], 404);
        }
        $code->revoke();
        $em->flush();

        return $this->json($this->present($code));
    }

    /** @return array<string, mixed> */
    private function present(InvitationCode $code): array
    {
        return [
            'id' => $code->getId(),
            'code' => InvitationCodeRepository::format($code->getCode()),
            'label' => $code->getLabel(),
            'role' => $code->getRole()->value,
            'maxUses' => $code->getMaxUses(),
            'usedCount' => $code->getUsedCount(),
            'expiresAt' => $code->getExpiresAt()?->format('c'),
            'revokedAt' => $code->getRevokedAt()?->format('c'),
            'createdAt' => $code->getCreatedAt()->format('c'),
            'status' => $code->status(),
        ];
    }
}
