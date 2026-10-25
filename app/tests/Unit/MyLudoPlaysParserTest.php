<?php

namespace App\Tests\Unit;

use App\MyLudo\PlaysParser;
use PHPUnit\Framework\TestCase;

final class MyLudoPlaysParserTest extends TestCase
{
    private const HEADER = "\xEF\xBB\xBF'ID;Titre;Sous-titre;Édition;Type;EAN;Date;Lieu;Durée;\"NB Joueurs\";Joueurs;Vainqueurs;Incomplète;Solo;Équipes;Coop;\"Coop Victoire\";\"Coop Score\";Exclue;\"En ligne\";Commentaire;\"Note moyenne\";\"Note ajustée\";\"Note personnelle\"\n";

    public function testLitUnePartieAvecSesJoueursSaDateEtSaNote(): void
    {
        $csv = self::HEADER . "92398;\"Under the Leaves\";;2025;basegame;0793969563968;2026-10-25;Maison;25;2;\"Moi (39), Mymy (41)\";Mymy;Non;Non;Non;Non;Non;0;Non;Non;Très bien;7,1;7,3;8\n";

        $plays = (new PlaysParser())->parse($csv);

        self::assertCount(1, $plays);
        self::assertSame('92398', $plays[0]['id']);
        self::assertSame('Under the Leaves', $plays[0]['title']);
        self::assertSame(2025, $plays[0]['year']);
        self::assertSame('2026-10-25', $plays[0]['playedAt']);
        self::assertSame('Maison', $plays[0]['place']);
        self::assertSame(2, $plays[0]['playersCount']);
        self::assertSame('Très bien', $plays[0]['comment']);
        self::assertSame(5, $plays[0]['rating']);
        self::assertFalse($plays[0]['excluded']);
    }

    public function testDeuxPartiesDuMemeJourOntDesClesDifferentes(): void
    {
        $ligne = fn(string $joueurs) => "90195;\"Spirits of the Wild\";Awakening;2025;basegame;;2026-10-23;Travail;20;2;\"$joueurs\";Moi;Non;Non;Non;Non;Non;0;Non;Non;;7,2;7,4;0\n";
        $plays = (new PlaysParser())->parse(self::HEADER . $ligne('Moi (5), Audrey (5)') . $ligne('Moi (12), Audrey (12)') . $ligne('Moi (5), Audrey (5)'));

        self::assertNotSame($plays[0]['key'], $plays[1]['key']);
        // Deux parties strictement identiques restent deux parties (la seconde porte un rang), sans changer la clé de la première.
        self::assertNotSame($plays[0]['key'], $plays[2]['key']);
        self::assertSame($plays[0]['key'], (new PlaysParser())->parse(self::HEADER . $ligne('Moi (5), Audrey (5)'))[0]['key']);
        self::assertSame($plays[2]['key'], (new PlaysParser())->parse(self::HEADER . $ligne('Moi (5), Audrey (5)') . $ligne('Moi (5), Audrey (5)'))[1]['key']);
        self::assertSame('Awakening', $plays[0]['subtitle']);
    }

    public function testSansTotalLesJoueursSontComptesEtUneDateInvalideEstSignalee(): void
    {
        $csv = self::HEADER . "1;Panorama;;2026;basegame;;31/02/2026;;20;;\"Moi (63), Audrey (42), Léo (10)\";Moi;Non;Non;Non;Non;Non;0;Non;Non;;7,1;7,1;0\n";

        $play = (new PlaysParser())->parse($csv)[0];

        self::assertSame(3, $play['playersCount']);
        self::assertNull($play['playedAt']);
        self::assertNull($play['rating']);
    }

    public function testLaNoteDuJeuDevientUnAvis(): void
    {
        self::assertSame(5, PlaysParser::rating(10.0));
        self::assertSame(5, PlaysParser::rating(7.0));
        self::assertNull(PlaysParser::rating(6.0));
        self::assertSame(1, PlaysParser::rating(3.0));
        self::assertNull(PlaysParser::rating(0.0));
    }

    public function testRefuseUnFichierQuiNEstPasUnExportDeParties(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PlaysParser())->parse("Nom;Prix\nCatan;30\n");
    }

    public function testLeFichierSansPartieDonneUneListeVide(): void
    {
        self::assertSame([], (new PlaysParser())->parse(self::HEADER . "\n"));
    }
}
