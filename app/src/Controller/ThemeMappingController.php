<?php

namespace App\Controller;

use App\Entity\ThemeMapping;
use App\Repository\ThemeMappingRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ThemeMappingController extends AbstractController
{
    // ── Public ──────────────────────────────────────────────────────────────

    /** Retourne tous les mappings — utilisé par le frontend pour afficher les thèmes. */
    #[Route('/api/theme-mappings', name: 'api_theme_mappings_list', methods: ['GET'])]
    public function list(ThemeMappingRepository $repo): JsonResponse
    {
        return $this->json($repo->findAllSorted());
    }

    // ── Admin ────────────────────────────────────────────────────────────────

    #[Route('/api/admin/theme-mappings', name: 'api_admin_theme_mappings_create', methods: ['POST'])]
    public function create(
        Request $request,
        ThemeMappingRepository $repo,
        EntityManagerInterface $em,
        JwtService $jwt,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user?->isAdmin()) return $this->json(['error' => 'Accès refusé.'], 403);

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['bggCategory']) || empty($data['themeGroup']) || empty($data['themeLabel'])) {
            return $this->json(['error' => 'Champs bggCategory, themeGroup, themeLabel requis.'], 422);
        }

        if ($repo->findOneBy(['bggCategory' => $data['bggCategory']])) {
            return $this->json(['error' => 'Ce mapping existe déjà.'], 409);
        }

        $mapping = (new ThemeMapping())
            ->setBggCategory(trim($data['bggCategory']))
            ->setThemeGroup(trim($data['themeGroup']))
            ->setThemeLabel(trim($data['themeLabel']))
            ->setThemeEmoji(trim($data['themeEmoji'] ?? '🏷️'));

        $em->persist($mapping);
        $em->flush();

        return $this->json($mapping, 201);
    }

    #[Route('/api/admin/theme-mappings/{id}', name: 'api_admin_theme_mappings_update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        ThemeMappingRepository $repo,
        EntityManagerInterface $em,
        JwtService $jwt,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user?->isAdmin()) return $this->json(['error' => 'Accès refusé.'], 403);

        $mapping = $repo->find($id);
        if (!$mapping) return $this->json(['error' => 'Mapping introuvable.'], 404);

        $data = json_decode($request->getContent(), true) ?? [];

        if (!empty($data['bggCategory'])) $mapping->setBggCategory(trim($data['bggCategory']));
        if (!empty($data['themeGroup']))  $mapping->setThemeGroup(trim($data['themeGroup']));
        if (!empty($data['themeLabel']))  $mapping->setThemeLabel(trim($data['themeLabel']));
        if (isset($data['themeEmoji']))   $mapping->setThemeEmoji(trim($data['themeEmoji']) ?: '🏷️');

        $em->flush();

        return $this->json($mapping);
    }

    #[Route('/api/admin/theme-mappings/{id}', name: 'api_admin_theme_mappings_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        ThemeMappingRepository $repo,
        EntityManagerInterface $em,
        JwtService $jwt,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user?->isAdmin()) return $this->json(['error' => 'Accès refusé.'], 403);

        $mapping = $repo->find($id);
        if (!$mapping) return $this->json(['error' => 'Mapping introuvable.'], 404);

        $em->remove($mapping);
        $em->flush();

        return $this->json(null, 204);
    }

    #[Route('/api/admin/theme-mappings/unmapped', name: 'api_admin_theme_mappings_unmapped', methods: ['GET'])]
    public function unmapped(
        Request $request,
        ThemeMappingRepository $repo,
        EntityManagerInterface $em,
        JwtService $jwt,
    ): JsonResponse {
        $user = AuthController::extractUser($request, $jwt, $em);
        if (!$user?->isAdmin()) return $this->json(['error' => 'Accès refusé.'], 403);

        return $this->json($repo->findUnmappedCategories());
    }
}
