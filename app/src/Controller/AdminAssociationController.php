<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AssociationGameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Ludothèque de l'association : lecture et retrait d'un jeu mal importé. L'ajout passe par les imports MyLudo et BGG. */
#[IsGranted('ROLE_ADMIN')]
final class AdminAssociationController extends AbstractController
{
    #[Route('/api/admin/association/games', name: 'api_admin_association_games', methods: ['GET'])]
    public function list(AssociationGameRepository $repository): JsonResponse
    {
        return $this->json(['count' => $repository->count(), 'games' => $repository->list()]);
    }

    #[Route('/api/admin/association/games/{id}', name: 'api_admin_association_game_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function remove(int $id, AssociationGameRepository $repository): JsonResponse
    {
        if (!$repository->remove($id)) {
            return $this->json(['error' => 'Ce jeu n\'est pas dans la ludothèque de l\'association.'], 404);
        }

        return $this->json(['removed' => $id]);
    }
}
