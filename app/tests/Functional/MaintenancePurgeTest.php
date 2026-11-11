<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MaintenancePurgeTest extends KernelTestCase
{
    public function testLaPurgeNeTouchePasLeRecentNiLEnCours(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement("DELETE FROM job_run WHERE command = 'zz:purge-test'");
        foreach ([
            ['vieux', "NOW() - INTERVAL '120 days'", "NOW() - INTERVAL '120 days'"],
            ['recent', "NOW() - INTERVAL '10 days'", "NOW() - INTERVAL '10 days'"],
            ['encours', "NOW() - INTERVAL '200 days'", 'NULL'],
        ] as [$nom, $debut, $fin]) {
            $connection->executeStatement("INSERT INTO job_run (command, arguments, started_at, finished_at) VALUES ('zz:purge-test', '$nom', $debut, $fin)");
        }

        $sec = $this->lancer(['--dry-run' => true]);
        self::assertStringContainsString('1 ligne(s) du journal', $sec->getDisplay());
        self::assertSame(3, $this->restantes($connection));

        $reel = $this->lancer([]);
        self::assertStringContainsString('Supprimé', $reel->getDisplay());
        self::assertSame(['encours', 'recent'], $connection->fetchFirstColumn("SELECT arguments FROM job_run WHERE command = 'zz:purge-test' ORDER BY arguments"));
    }

    public function testLeJournalSeConserveAuMoinsSeptJours(): void
    {
        self::bootKernel();

        $testeur = $this->lancer(['--jobs-days' => 3]);

        self::assertSame(1, $testeur->getStatusCode());
    }

    /** @param array<string, mixed> $options */
    private function lancer(array $options): CommandTester
    {
        $application = new Application(self::$kernel);
        $testeur = new CommandTester($application->find('app:maintenance:purge'));
        $testeur->execute($options);

        return $testeur;
    }

    private function restantes(Connection $connection): int
    {
        return (int) $connection->fetchOne("SELECT COUNT(*) FROM job_run WHERE command = 'zz:purge-test'");
    }
}
