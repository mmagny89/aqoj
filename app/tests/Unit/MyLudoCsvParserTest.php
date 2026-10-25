<?php

namespace App\Tests\Unit;

use App\MyLudo\CsvParser;
use PHPUnit\Framework\TestCase;

final class MyLudoCsvParserTest extends TestCase
{
    private const HEADER = "'ID;Titre;Sous-titre;Édition;Type;EAN;\"Prix indicatif\";Dimensions;Poids;Joueur(s);Durée;Age(s);Langues;Univers;Gamme(s);Catégorie(s);Thème(s);Mécanisme(s);Éditeur(s);Auteur(s);Illustrateur(s);\"Date d'acquisition\";Propriétaire;Cadeaux;Emplacement;\"Prix d'achat\";Gratuit;État;Privé;Protégé;Organisé;Personnalisé;\"Figurines peintes\";Commentaire;Référence;Référent;\"Dernière partie\";\"Note moyenne\";\"Note ajustée\";\"Note personnelle\"\n";

    public function testLitUnExportMyLudoAvecSesParticularites(): void
    {
        $csv = self::HEADER
            . '95888;"Les Défis De L\'as D\'or";;2026;basegame;;0;;;"2 — 6";15;8+;français;;;"Jeu de Cartes";"Jeu De Société";"Coopération";"Fabryka Kart";"M. Damien";"M. Damien";2026-04-18;;;;;0;;non;non;non;non;non;;;;;6;7,2;0' . "\n"
            . '32889;Sakura;"Les Jardins de l\'Empereur";2019;basegame;;23,5;;;"2 — 6";"20 — 40";10+;français;;;"Jeu de plateau";Abstrait;Programmation;Origames;"Reiner Knizia";"Kevin Hong";2026-04-14;;;;;0;;non;non;non;non;non;;;;;6,7;7;7,5' . "\n"
            . '1;"Azul : Vitraux";;2018;expansion;;;;;"2 — 4";30;8+;français;;;;;;;;;;;;;;;;;;;;;;;;;;;' . "\n"
            . ';;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;' . "\n";

        $rows = (new CsvParser())->parse($csv);

        self::assertCount(3, $rows);
        self::assertSame('Les Défis De L\'as D\'or', $rows[0]['title']);
        self::assertSame(2026, $rows[0]['year']);
        self::assertNull($rows[0]['rating']);
        self::assertFalse($rows[0]['isExpansion']);
        self::assertSame('Les Jardins de l\'Empereur', $rows[1]['subtitle']);
        self::assertSame(7.5, $rows[1]['rating']);
        self::assertSame('20 — 40', $rows[1]['duration']);
        self::assertTrue($rows[2]['isExpansion']);
        self::assertSame([0, 1, 2], array_column($rows, 'index'));
    }

    public function testAccepteUnBomEtUnEnTeteSansApostrophe(): void
    {
        $rows = (new CsvParser())->parse("\xEF\xBB\xBFID;Titre;Édition;Type\n5;Lama;2019;basegame\n");

        self::assertSame('Lama', $rows[0]['title']);
        self::assertSame(2019, $rows[0]['year']);
    }

    public function testRefuseUnFichierQuiNEstPasUnExportMyLudo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Titre/');

        (new CsvParser())->parse("nom;annee\nLama;2019\n");
    }

    public function testRefuseUnFichierVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CsvParser())->parse('');
    }
}
