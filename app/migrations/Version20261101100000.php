<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ludothèque de l\'association : jeux renseignés par les administrateurs (import MyLudo ou BGG)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS association_game (
                game_id  INTEGER NOT NULL PRIMARY KEY,
                added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                added_by INTEGER DEFAULT NULL,
                CONSTRAINT fk_association_game_game FOREIGN KEY (game_id) REFERENCES game(id) ON DELETE CASCADE,
                CONSTRAINT fk_association_game_user FOREIGN KEY (added_by) REFERENCES app_user(id) ON DELETE SET NULL
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS association_game');
    }
}
