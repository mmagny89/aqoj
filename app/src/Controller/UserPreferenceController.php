<?php

namespace App\Controller;

use App\Repository\UserPreferenceRepository;
use App\Service\MechanicFamilyResolver;
use App\Service\UserPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserPreferenceController extends AbstractController
{
    use CurrentUser;

    /** Familles proposées par le questionnaire de goûts (moteurs, support, caractéristiques). */
    private const TASTE_FAMILIES = [
        ...MechanicFamilyResolver::ENGINE_FAMILIES,
        'card_play', 'spatial_placement', 'movement', 'resource_management',
        'resolution', 'uncertainty', 'cooperation', 'scoring',
        ...MechanicFamilyResolver::EXTRA_FAMILIES,
    ];

    private const TASTE_MAX = 6;

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

    /** Mémorise la table choisie sur l'accueil : 1 à 8 joueurs, 15 à 240 minutes par pas de 15. */
    #[Route('/api/user/table', name: 'api_user_table_put', methods: ['PUT'])]
    public function saveTable(
        Request $request,
        UserPreferenceRepository $prefRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $body    = json_decode($request->getContent(), true);
        $players = is_array($body) ? ($body['players'] ?? null) : null;
        $maxTime = is_array($body) ? ($body['maxTime'] ?? null) : null;

        if (!is_int($players) || $players < 1 || $players > 8) {
            return $this->json(['error' => 'Nombre de joueurs : de 1 à 8.'], 400);
        }
        if (!is_int($maxTime) || $maxTime < 15 || $maxTime > 240 || $maxTime % 15 !== 0) {
            return $this->json(['error' => 'Durée : de 15 à 240 minutes, par pas de 15.'], 400);
        }

        $pref = $prefRepository->findOrCreate($user)->setTable($players, $maxTime);
        $em->persist($pref);
        $em->flush();

        return $this->json(['table' => $pref->getTable()]);
    }

    /** Enregistre les goûts déclarés par le joueur ; ils servent tant que son historique ne suffit pas. */
    #[Route('/api/user/taste', name: 'api_user_taste_put', methods: ['PUT'])]
    public function saveTaste(
        Request $request,
        UserPreferenceRepository $prefRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $families = json_decode($request->getContent(), true)['families'] ?? null;
        if (!is_array($families) || !array_is_list($families)) {
            return $this->json(['error' => 'Liste de familles attendue.'], 400);
        }

        $families = array_values(array_unique(array_filter($families, 'is_string')));
        if (array_diff($families, self::TASTE_FAMILIES) !== []) {
            return $this->json(['error' => 'Famille inconnue.'], 400);
        }
        if (count($families) > self::TASTE_MAX) {
            return $this->json(['error' => 'Six goûts au plus.'], 400);
        }

        $pref = $prefRepository->findOrCreate($user)->setDeclaredFamilies($families);
        $em->persist($pref);
        $em->flush();

        return $this->json(['declaredFamilies' => $pref->getDeclaredFamilies()]);
    }
}
