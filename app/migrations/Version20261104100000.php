<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Description française des jeux (traduction automatique de la description BGG, mise en cache)';
    }

    public function up(Schema $schema): void
    {
        // Le hash est celui de la description anglaise traduite : si BGG la change, la traduction est refaite.
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS description_fr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS description_fr_hash VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS description_fr_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Dernier essai, réussi ou non : un service en panne ne doit pas être rappelé à chaque consultation.
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS description_fr_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS description_fr_attempt_at');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS description_fr_at');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS description_fr_hash');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS description_fr');
    }
}
