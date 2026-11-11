<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Goûts déclarés par le joueur (démarrage à froid des recommandations)';
    }

    public function up(Schema $schema): void
    {
        // Colonne à part : le recalcul des préférences ne doit jamais effacer ce que le joueur a déclaré.
        $this->addSql("ALTER TABLE user_preference ADD COLUMN IF NOT EXISTS declared_families JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_preference DROP COLUMN IF EXISTS declared_families');
    }
}
