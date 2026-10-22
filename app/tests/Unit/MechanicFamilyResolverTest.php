<?php

namespace App\Tests\Unit;

use App\Repository\MechanicMappingRepository;
use App\Service\MechanicFamilyResolver;
use PHPUnit\Framework\TestCase;

final class MechanicFamilyResolverTest extends TestCase
{
    private MechanicFamilyResolver $resolver;

    protected function setUp(): void
    {
        $repository = $this->createStub(MechanicMappingRepository::class);
        $repository->method('getFamilyMap')->willReturn([
            'Worker Placement' => 'worker_placement',
            'Deck, Bag, and Pool Building' => 'deck_building',
            'Open Drafting' => 'deck_building',
            'Dice Rolling' => 'randomness',
        ]);

        $this->resolver = new MechanicFamilyResolver($repository);
    }

    public function testLesMoteursSontTriesParDensiteDecroissante(): void
    {
        $result = $this->resolver->resolve(['Worker Placement', 'Open Drafting', 'Deck, Bag, and Pool Building']);

        self::assertSame(['deck_building', 'worker_placement'], $result['detectedEngines']);
    }

    public function testUneFamilleDeSupportNEstJamaisUnMoteur(): void
    {
        $result = $this->resolver->resolve(['Dice Rolling']);

        self::assertSame(['randomness'], $result['families']);
        self::assertSame([], $result['detectedEngines']);
    }

    public function testUneMecaniqueNonMappeeEstIgnoree(): void
    {
        $result = $this->resolver->resolve(['Mécanique inconnue']);

        self::assertSame(['families' => [], 'detectedEngines' => []], $result);
    }
}
