<?php

namespace App\Controller;

use App\Repository\UserPreferenceRepository;
use App\Service\JwtService;
use App\Service\UserPreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserPreferenceController extends AbstractController
{
    /** Retourne les préférences de l'utilisateur connecté (null si pas encore calculées). */
    #[Route('/api/user/preferences', name: 'api_user_preferences_get', methods: ['GET'])]
    public function get(
        Request $request,
        JwtService $jwt,
        EntityManagerInterface $em,
        UserPreferenceRepository $prefRepository,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $pref = $prefRepository->find($user->getId());

        return $this->json($pref?->jsonSerialize());
    }

    /** Force le recalcul des préférences. */
    #[Route('/api/user/preferences/recompute', name: 'api_user_preferences_recompute', methods: ['POST'])]
    public function recompute(
        Request $request,
        JwtService $jwt,
        EntityManagerInterface $em,
        UserPreferenceService $prefService,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $pref = $prefService->recompute($user);

        return $this->json($pref->jsonSerialize());
    }
}
