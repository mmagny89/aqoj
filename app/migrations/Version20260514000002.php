<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bgg_username to app_user and create user_game collection table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD COLUMN bgg_username VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_BGG ON app_user (bgg_username)');

        $this->addSql('CREATE TABLE user_game (
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            PRIMARY KEY (user_id, game_id)
        )');
        $this->addSql('ALTER TABLE user_game ADD CONSTRAINT FK_USER_GAME_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_game ADD CONSTRAINT FK_USER_GAME_GAME FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_USER_GAME_USER ON user_game (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_game');
        $this->addSql('DROP INDEX UNIQ_APP_USER_BGG');
        $this->addSql('ALTER TABLE app_user DROP COLUMN bgg_username');
    }
}
