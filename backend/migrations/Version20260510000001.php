<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: game, user_profile, game_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game (
            id SERIAL NOT NULL,
            bgg_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            min_players INT NOT NULL DEFAULT 1,
            max_players INT NOT NULL DEFAULT 1,
            playing_time INT NOT NULL DEFAULT 0,
            complexity DOUBLE PRECISION NOT NULL DEFAULT 0,
            categories JSON NOT NULL,
            mechanics JSON NOT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            year_published INT DEFAULT NULL,
            rating_bgg DOUBLE PRECISION DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C4E7E4E8 ON game (bgg_id)');

        $this->addSql('CREATE TABLE user_profile (
            id SERIAL NOT NULL,
            bgg_username VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D95AB405514956FD ON user_profile (bgg_username)');

        $this->addSql('CREATE TABLE game_session (
            id SERIAL NOT NULL,
            user_profile_id INT DEFAULT NULL,
            game_id INT NOT NULL,
            players_count INT NOT NULL,
            rating INT DEFAULT NULL,
            note TEXT DEFAULT NULL,
            played_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            context VARCHAR(100) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_6BE6FBF8A76ED395 FOREIGN KEY (user_profile_id) REFERENCES user_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_6BE6FBF8E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE user_profile');
        $this->addSql('DROP TABLE game');
    }
}
