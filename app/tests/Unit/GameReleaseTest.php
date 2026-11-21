<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameReleaseTest extends TestCase
{
    private const NOW = '2026-11-21 12:00:00';

    /** @return iterable<string, array{int, ?string, bool, ?string}> */
    public static function cas(): iterable
    {
        yield 'année en cours, découvert hier' => [2026, '-1 day', false, 'new'];
        yield 'année en cours, découvert il y a 31 jours' => [2026, '-31 days', false, null];
        yield 'année en cours, import initial' => [2026, null, false, null];
        yield 'année précédente, découvert hier (passage de janvier)' => [2025, '-1 day', false, 'new'];
        yield 'ancien jeu redécouvert hier' => [2015, '-1 day', false, null];
        yield 'année à venir' => [2027, null, false, 'upcoming'];
        yield 'extension récente' => [2026, '-1 day', true, null];
    }

    #[DataProvider('cas')]
    public function testEtiquetteDeSortie(int $annee, ?string $decouvert, bool $extension, ?string $attendu): void
    {
        $now  = new \DateTimeImmutable(self::NOW);
        $game = (new Game())->setYearPublished($annee)->setIsExpansion($extension);
        if ($decouvert !== null) {
            (new \ReflectionProperty(Game::class, 'discoveredAt'))->setValue($game, $now->modify($decouvert));
        }

        self::assertSame($attendu, $game->releaseAt($now));
    }
}
