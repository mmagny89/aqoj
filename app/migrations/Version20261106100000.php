<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parties : clé d\'import (MyLudo) pour qu\'un fichier réimporté ne double pas les parties';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_session ADD COLUMN IF NOT EXISTS import_key VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_game_session_import ON game_session (user_id, import_key) WHERE import_key IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_game_session_import');
        $this->addSql('ALTER TABLE game_session DROP COLUMN IF EXISTS import_key');
    }
}
