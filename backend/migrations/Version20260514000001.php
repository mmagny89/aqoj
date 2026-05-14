<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_user table and link game_session to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_EMAIL ON app_user (email)');

        $this->addSql('ALTER TABLE game_session ADD COLUMN user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_GAME_SESSION_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_session DROP CONSTRAINT FK_GAME_SESSION_USER');
        $this->addSql('ALTER TABLE game_session DROP COLUMN user_id');
        $this->addSql('DROP TABLE app_user');
    }
}
