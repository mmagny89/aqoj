<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retours sur les propositions : « pas intéressé » ou « déjà joué, bof », un par joueur et par jeu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS game_feedback (
                user_id    INTEGER      NOT NULL,
                game_id    INTEGER      NOT NULL,
                verdict    VARCHAR(16)  NOT NULL,
                created_at TIMESTAMP(0) NOT NULL DEFAULT NOW(),
                PRIMARY KEY (user_id, game_id),
                CONSTRAINT fk_game_feedback_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
                CONSTRAINT fk_game_feedback_game FOREIGN KEY (game_id) REFERENCES game(id) ON DELETE CASCADE,
                CONSTRAINT chk_game_feedback_verdict CHECK (verdict IN ('not_interested', 'played_meh'))
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS game_feedback');
    }
}
