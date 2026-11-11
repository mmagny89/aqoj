<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalogue\CatalogueImporter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogueImporterTest extends KernelTestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($this->file, <<<'CSV'
            id,name,yearpublished,rank,bayesaverage,usersrated,is_expansion
            990101,Zzimp Un,2001,10,7.5,100,0
            990102,Zzimp Deux,2002,0,0,0,0
            990103,Zzimp Extension,2003,0,0,0,1
            CSV);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testCreeEtMetAJourSansTouchaALaNoteDeLApi(): void
    {
        self::bootKernel();
        $importer = self::getContainer()->get(CatalogueImporter::class);
        $connection = self::getContainer()->get(Connection::class);

        $premier = $importer->import('bgg', new \SplFileInfo($this->file));
        self::assertSame([3, 2, 0, 1], [$premier->read, $premier->created, $premier->updated, $premier->skippedExpansions]);

        $connection->executeStatement("UPDATE game SET source = 'bgg_api', rating_bgg = 8.8 WHERE bgg_id = '990101'");
        $second = $importer->import('bgg', new \SplFileInfo($this->file), includeExpansions: true);

        self::assertSame([1, 2], [$second->created, $second->updated]);
        self::assertSame(8.8, (float) $connection->fetchOne("SELECT rating_bgg FROM game WHERE bgg_id = '990101'"));
        self::assertSame(3, (int) $connection->fetchOne("SELECT count(*) FROM game WHERE bgg_id LIKE '9901%'"));
    }

    public function testLeDryRunNEcritRien(): void
    {
        self::bootKernel();
        $importer = self::getContainer()->get(CatalogueImporter::class);

        $report = $importer->import('bgg', new \SplFileInfo($this->file), dryRun: true);

        self::assertSame([3, 0, 0], [$report->read, $report->created, $report->updated]);
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne("SELECT count(*) FROM game WHERE bgg_id LIKE '9901%'"));
    }

    public function testUneSourceInconnueEstRefusee(): void
    {
        self::bootKernel();

        $this->expectException(\InvalidArgumentException::class);

        self::getContainer()->get(CatalogueImporter::class)->import('inconnue', new \SplFileInfo($this->file));
    }
}
