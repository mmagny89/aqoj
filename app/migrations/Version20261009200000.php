<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute game.last_fetch_attempt_at : dernier appel BGG, réussi ou non, pour ne pas rappeler un jeu absent ou en erreur à chaque passage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS last_fetch_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS last_fetch_attempt_at');
    }
}
