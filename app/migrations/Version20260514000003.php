<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop user_profile table and user_profile_id from game_session (replaced by app_user auth)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_session DROP CONSTRAINT fk_6be6fbf8a76ed395');
        $this->addSql('ALTER TABLE game_session DROP COLUMN user_profile_id');
        $this->addSql('DROP TABLE user_profile');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_profile (
            id SERIAL NOT NULL,
            bgg_username VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D95AB405514956FD ON user_profile (bgg_username)');
        $this->addSql('ALTER TABLE game_session ADD COLUMN user_profile_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT fk_6be6fbf8a76ed395 FOREIGN KEY (user_profile_id) REFERENCES user_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
