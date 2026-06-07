<?php

namespace App\Service;

use App\Entity\Game;
use App\Repository\MechanicMappingRepository;

class MechanicFamilyResolver
{
    /**
     * Les 6 familles qui peuvent être le moteur central d'un jeu.
     * Cette liste est une constante du modèle Engelstein, pas une propriété de la base.
     */
    public const ENGINE_FAMILIES = [
        'worker_placement',
        'deck_building',
        'engine_building',
        'area_control',
        'hand_management',
        'auction',
    ];

    /**
     * Familles supplémentaires hors taxonomie Engelstein.
     * Elles ne peuvent jamais être un moteur central, mais sont utiles
     * pour l'affichage et les filtres de recommandations.
     */
    public const EXTRA_FAMILIES = [
        'solo',
        'real_time',
        'dexterity',
        'legacy',
    ];

    private ?array $familyMap = null;

    public function __construct(private readonly MechanicMappingRepository $repository) {}

    /**
     * Retourne [families, detectedEngines] pour une liste de mécaniques BGG.
     *
     * - families        : toutes les familles présentes (support + engines)
     * - detectedEngines : familles dans ENGINE_FAMILIES ayant au moins une mécanique matchée,
     *                     triées par nombre de mécaniques matchées (signal de densité)
     *
     * @return array{families: string[], detectedEngines: string[]}
     */
    public function resolve(array $bggMechanics): array
    {
        $familyMap = $this->getFamilyMap();

        $families     = [];
        $engineCounts = []; // famille => nombre de mécaniques matchées

        foreach ($bggMechanics as $mechanic) {
            if (!isset($familyMap[$mechanic])) {
                continue;
            }
            $family = $familyMap[$mechanic];
            $families[$family] = true;

            if (in_array($family, self::ENGINE_FAMILIES, true)) {
                $engineCounts[$family] = ($engineCounts[$family] ?? 0) + 1;
            }
        }

        // Trie les engines par densité décroissante (le moteur principal en premier)
        arsort($engineCounts);

        return [
            'families'        => array_values(array_keys($families)),
            'detectedEngines' => array_values(array_keys($engineCounts)),
        ];
    }

    public function resolveForGame(Game $game): array
    {
        return $this->resolve($game->getMechanics());
    }

    public function getFamilyMap(): array
    {
        if ($this->familyMap === null) {
            $this->familyMap = $this->repository->getFamilyMap();
        }

        return $this->familyMap;
    }

    public function invalidateCache(): void
    {
        $this->familyMap = null;
    }
}
