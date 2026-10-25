<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalogue;

use App\Catalogue\Import\CsvBggImporter;
use App\Catalogue\Import\InvalidSourceException;
use PHPUnit\Framework\TestCase;

final class CsvBggImporterTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'csv');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testLitLesLignesExploitablesEtIgnoreLesAutres(): void
    {
        file_put_contents($this->file, <<<'CSV'
            id,name,yearpublished,rank,bayesaverage,usersrated,is_expansion
            1,Die Macher,1986,522,7.1,6020,0
            2,,1990,0,0,0,0
            3,"Extension, la suite",2001,,0,12,1
            4,ligne incomplète
            CSV);

        $jeux = iterator_to_array((new CsvBggImporter())->import(new \SplFileInfo($this->file)), false);

        self::assertCount(2, $jeux);
        self::assertSame('1', $jeux[0]->bggId);
        self::assertSame(522, $jeux[0]->bggRank);
        self::assertSame(7.1, $jeux[0]->ratingBgg);
        self::assertFalse($jeux[0]->isExpansion);
        self::assertSame('Extension, la suite', $jeux[1]->name);
        self::assertNull($jeux[1]->bggRank);
        self::assertNull($jeux[1]->ratingBgg);
        self::assertTrue($jeux[1]->isExpansion);
    }

    public function testRefuseUnCsvSansLesColonnesRequises(): void
    {
        file_put_contents($this->file, "id,name\n1,Jeu\n");

        $this->expectException(InvalidSourceException::class);

        iterator_to_array((new CsvBggImporter())->import(new \SplFileInfo($this->file)));
    }
}
