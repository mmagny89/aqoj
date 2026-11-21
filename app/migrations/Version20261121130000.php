<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Une partie enregistrée annule l\'envie de jouer (« J\'aimerais jouer ») sur ce jeu, quelle que soit l\'origine de la partie';
    }

    public function up(Schema $schema): void
    {
        // Un déclencheur plutôt qu'un appel par contrôleur : la saisie et l'import MyLudo écrivent dans game_session.
        $this->addSql("
            CREATE OR REPLACE FUNCTION game_session_clear_want_to_play() RETURNS trigger AS \$\$
            BEGIN
                IF NEW.user_id IS NOT NULL THEN
                    DELETE FROM game_feedback
                    WHERE user_id = NEW.user_id AND game_id = NEW.game_id AND verdict = 'want_to_play';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_clear_want_to_play ON game_session');
        $this->addSql('
            CREATE TRIGGER trg_game_session_clear_want_to_play
            AFTER INSERT ON game_session
            FOR EACH ROW EXECUTE FUNCTION game_session_clear_want_to_play()
        ');
        // Les envies déjà satisfaites par une partie existante.
        $this->addSql("
            DELETE FROM game_feedback f
            WHERE f.verdict = 'want_to_play'
              AND EXISTS (SELECT 1 FROM game_session gs WHERE gs.user_id = f.user_id AND gs.game_id = f.game_id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_clear_want_to_play ON game_session');
        $this->addSql('DROP FUNCTION IF EXISTS game_session_clear_want_to_play()');
    }
}
