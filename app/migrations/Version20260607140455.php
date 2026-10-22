<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607140455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme name_fr en name_original ; name devient le nom d\'affichage (FR ou EN)';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajouter name_original (stocke le nom BGG anglais d'origine)
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS name_original VARCHAR(255) DEFAULT NULL');
        // 2. Copier le nom actuel (EN) vers name_original pour tous les jeux
        $this->addSql('UPDATE game SET name_original = name WHERE name_original IS NULL');
        // 3. Supprimer name_fr (détection trop permissive → on repart proprement)
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS name_fr');
        // 4. Ajouter is_expansion_type pour stocker le type BGG brut (manquait)
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS bgg_type VARCHAR(30) DEFAULT \'boardgame\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS name_fr VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS name_original');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS bgg_type');
    }
}
