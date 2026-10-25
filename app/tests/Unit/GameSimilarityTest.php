<?php

namespace App\Tests\Unit;

use App\Entity\Game;
use App\Service\RecommendationService;
use PHPUnit\Framework\TestCase;

final class GameSimilarityTest extends TestCase
{
    private RecommendationService $service;

    protected function setUp(): void
    {
        // computeSimilarity ne se sert d'aucune dépendance.
        $this->service = (new \ReflectionClass(RecommendationService::class))->newInstanceWithoutConstructor();
    }

    private function jeu(array $familles = [], array $moteurs = [], array $categories = [], array $mecaniques = [], float $complexite = 2.0, int $min = 2, int $max = 4, int $duree = 60): Game
    {
        return (new Game())
            ->setMechanicFamilies($familles)->setDetectedEngines($moteurs)->setCategories($categories)->setMechanics($mecaniques)
            ->setComplexity($complexite)->setMinPlayers($min)->setMaxPlayers($max)->setPlayingTime($duree);
    }

    public function testUnJeuIdentiqueVautCent(): void
    {
        $jeu = $this->jeu(['worker_placement', 'randomness'], ['worker_placement'], ['Economic'], ['Worker Placement']);

        self::assertSame(100, $this->service->computeSimilarity($jeu, $jeu));
    }

    public function testUnMoteurPartagePeseTroisFoisUneFamilleDeSupport(): void
    {
        $ref = $this->jeu(['worker_placement', 'randomness'], ['worker_placement']);
        $memeMoteur = $this->jeu(['worker_placement'], ['worker_placement']);
        $memeSupport = $this->jeu(['randomness']);

        self::assertGreaterThan(
            $this->service->computeSimilarity($ref, $memeSupport),
            $this->service->computeSimilarity($ref, $memeMoteur),
        );
    }

    public function testUneFichePauvreNeGagnePasDePointsGratuits(): void
    {
        $ref = $this->jeu(['worker_placement'], ['worker_placement'], ['Economic'], ['Worker Placement']);
        $vide = $this->jeu(complexite: 0.0, min: 1, max: 1, duree: 0);
        $opposé = $this->jeu(['cooperation'], [], ['Horror'], ['Cooperative Game'], complexite: 4.5, min: 6, max: 8, duree: 10);

        self::assertLessThanOrEqual(10, $this->service->computeSimilarity($ref, $vide));
        self::assertLessThanOrEqual(5, $this->service->computeSimilarity($ref, $opposé));
    }

    public function testLaComplexiteEtLaDureeRapprochentDeuxJeux(): void
    {
        $ref = $this->jeu(['randomness'], complexite: 2.0, duree: 30);
        $proche = $this->jeu(['randomness'], complexite: 2.2, duree: 35);
        $loin = $this->jeu(['randomness'], complexite: 4.2, duree: 180);

        self::assertGreaterThan($this->service->computeSimilarity($ref, $loin), $this->service->computeSimilarity($ref, $proche));
    }
}
