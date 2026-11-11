<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table habituelle du joueur (nombre de joueurs et durée choisis sur l\'accueil)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_preference ADD COLUMN IF NOT EXISTS table_players SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_preference ADD COLUMN IF NOT EXISTS table_max_time SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_preference DROP COLUMN IF EXISTS table_max_time');
        $this->addSql('ALTER TABLE user_preference DROP COLUMN IF EXISTS table_players');
    }
}
