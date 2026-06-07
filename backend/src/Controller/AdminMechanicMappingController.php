<?php

namespace App\Controller;

use App\Entity\MechanicMapping;
use App\Repository\MechanicMappingRepository;
use App\Service\JwtService;
use App\Service\MechanicFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/mechanic-mappings')]
class AdminMechanicMappingController extends AbstractController
{
    // Les 6 moteurs centraux (définis par le modèle Engelstein, pas en base)
    public const ENGINE_FAMILIES = [
        'worker_placement' => 'Placement d\'Ouvriers',
        'deck_building'    => 'Construction de Deck',
        'engine_building'  => 'Construction de Moteur',
        'area_control'     => 'Contrôle de Zone',
        'hand_management'  => 'Gestion de Main',
        'auction'          => 'Enchères',
    ];

    // Familles de support
    public const SUPPORT_FAMILIES = [
        'card_play'           => 'Jeu de Cartes',
        'spatial_placement'   => 'Placement Spatial',
        'movement'            => 'Mouvement',
        'resource_management' => 'Gestion de Ressources',
        'resolution'          => 'Résolution',
        'uncertainty'         => 'Hasard & Incertitude',
        'cooperation'         => 'Coopération',
        'scoring'             => 'Marquage de Points',
    ];

    public const ENGELSTEIN_FAMILIES = self::ENGINE_FAMILIES + self::SUPPORT_FAMILIES;

    public function __construct(
        private readonly MechanicMappingRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly JwtService $jwt,
        private readonly MechanicFamilyResolver $familyResolver,
    ) {}

    #[Route('', name: 'admin_mechanic_mappings_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json([
            'mappings' => $this->repository->findBy([], ['engelsteinFamily' => 'ASC', 'bggMechanic' => 'ASC']),
            'families' => self::ENGELSTEIN_FAMILIES,
        ]);
    }

    #[Route('', name: 'admin_mechanic_mappings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $error = $this->validate($data);
        if ($error) {
            return $this->json(['error' => $error], 422);
        }

        if ($this->repository->findOneBy(['bggMechanic' => $data['bggMechanic']])) {
            return $this->json(['error' => 'Cette mécanique BGG existe déjà.'], 409);
        }

        $mapping = (new MechanicMapping())
            ->setBggMechanic(trim($data['bggMechanic']))
            ->setEngelsteinFamily($data['engelsteinFamily'])
            ->setDescription(isset($data['description']) ? trim($data['description']) : null);

        $this->em->persist($mapping);
        $this->em->flush();

        return $this->json($mapping, 201);
    }

    #[Route('/{id}', name: 'admin_mechanic_mappings_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $mapping = $this->repository->find($id);
        if (!$mapping) {
            return $this->json(['error' => 'Mapping introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $error = $this->validate($data);
        if ($error) {
            return $this->json(['error' => $error], 422);
        }

        $existing = $this->repository->findOneBy(['bggMechanic' => $data['bggMechanic']]);
        if ($existing && $existing->getId() !== $id) {
            return $this->json(['error' => 'Cette mécanique BGG est déjà utilisée par un autre mapping.'], 409);
        }

        $mapping
            ->setBggMechanic(trim($data['bggMechanic']))
            ->setEngelsteinFamily($data['engelsteinFamily'])
            ->setDescription(isset($data['description']) ? trim($data['description']) : null);

        $this->em->flush();

        return $this->json($mapping);
    }

    #[Route('/{id}', name: 'admin_mechanic_mappings_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $mapping = $this->repository->find($id);
        if (!$mapping) {
            return $this->json(['error' => 'Mapping introuvable.'], 404);
        }

        $this->em->remove($mapping);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function validate(array $data): ?string
    {
        if (empty($data['bggMechanic'])) {
            return 'Le champ bggMechanic est requis.';
        }
        if (empty($data['engelsteinFamily'])) {
            return 'Le champ engelsteinFamily est requis.';
        }
        if (!array_key_exists($data['engelsteinFamily'], self::ENGELSTEIN_FAMILIES)) {
            return 'Famille Engelstein invalide.';
        }

        return null;
    }

    #[Route('/unmapped', name: 'admin_mechanic_mappings_unmapped', methods: ['GET'])]
    public function unmapped(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $conn = $this->em->getConnection();

        $rows = $conn->fetchAllAssociative("
            SELECT sub.mechanic, COUNT(*) AS game_count
            FROM (
                SELECT json_array_elements_text(mechanics) AS mechanic
                FROM game
                WHERE mechanics::text != '[]'
            ) sub
            LEFT JOIN mechanic_mapping mm ON mm.bgg_mechanic = sub.mechanic
            WHERE mm.bgg_mechanic IS NULL
            GROUP BY sub.mechanic
            ORDER BY game_count DESC
        ");

        return $this->json($rows);
    }

    #[Route('/recompute', name: 'admin_mechanic_mappings_recompute', methods: ['POST'])]
    public function recompute(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $this->familyResolver->invalidateCache();

        $conn      = $this->em->getConnection();
        $familyMap = $this->familyResolver->getFamilyMap();
        $engines   = array_flip(MechanicFamilyResolver::ENGINE_FAMILIES);
        $batchSize = 200;
        $offset    = 0;
        $count     = 0;

        while (true) {
            $rows = $conn->fetchAllAssociative(
                'SELECT id, mechanics FROM game ORDER BY id ASC LIMIT ? OFFSET ?',
                [$batchSize, $offset]
            );

            if (empty($rows)) {
                break;
            }

            $casesFamilies  = [];
            $casesEngines   = [];
            $paramsFamilies = [];
            $paramsEngines  = [];
            $ids            = [];

            foreach ($rows as $row) {
                $mechanics    = json_decode($row['mechanics'], true) ?? [];
                $families     = [];
                $engineCounts = [];

                foreach ($mechanics as $mechanic) {
                    if (!isset($familyMap[$mechanic])) {
                        continue;
                    }
                    $family = $familyMap[$mechanic];
                    $families[$family] = true;
                    if (isset($engines[$family])) {
                        $engineCounts[$family] = ($engineCounts[$family] ?? 0) + 1;
                    }
                }

                arsort($engineCounts);

                $casesFamilies[]  = 'WHEN id = ? THEN ?::jsonb';
                $paramsFamilies[] = $row['id'];
                $paramsFamilies[] = json_encode(array_values(array_keys($families)));

                $casesEngines[]  = 'WHEN id = ? THEN ?::jsonb';
                $paramsEngines[] = $row['id'];
                $paramsEngines[] = json_encode(array_values(array_keys($engineCounts)));

                $ids[] = $row['id'];
                $count++;
            }

            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $conn->executeStatement(
                'UPDATE game SET
                    mechanic_families = CASE ' . implode(' ', $casesFamilies) . ' END,
                    detected_engines  = CASE ' . implode(' ', $casesEngines) . ' END
                WHERE id IN (' . $placeholders . ')',
                array_merge($paramsFamilies, $paramsEngines, $ids)
            );

            $offset += $batchSize;
        }

        return $this->json(['updated' => $count]);
    }

    private function isAdmin(Request $request): bool
    {
        $user = AuthController::extractUser($request, $this->jwt, $this->em);

        return $user !== null && $user->isAdmin();
    }
}
