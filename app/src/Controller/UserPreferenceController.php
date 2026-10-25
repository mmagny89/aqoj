<?php

namespace App\Controller;

use App\Repository\UserPreferenceRepository;
use App\Service\UserPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class UserPreferenceController extends AbstractController
{
    use CurrentUser;

    /** Retourne les préférences de l'utilisateur connecté (null si pas encore calculées). */
    #[Route('/api/user/preferences', name: 'api_user_preferences_get', methods: ['GET'])]
    public function get(
        UserPreferenceRepository $prefRepository,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $pref = $prefRepository->find($user->getId());

        return $this->json($pref?->jsonSerialize());
    }

    /** Force le recalcul des préférences. */
    #[Route('/api/user/preferences/recompute', name: 'api_user_preferences_recompute', methods: ['POST'])]
    public function recompute(
        UserPreferenceService $prefService,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $pref = $prefService->recompute($user);

        return $this->json($pref->jsonSerialize());
    }
}
