<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607134819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute min_age et name_fr à la table game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS min_age INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS name_fr VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS min_age');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS name_fr');
    }
}
