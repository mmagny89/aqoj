<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retours automatiques : un jeu déjà joué et bien noté coche « j\'ai joué, et j\'aime », mal noté « déjà joué, bof »';
    }

    public function up(Schema $schema): void
    {
        // `user` = choix du joueur (écarte le jeu des listes), `auto` = déduit des parties et des notes (ne fait que cocher).
        $this->addSql("ALTER TABLE game_feedback ADD COLUMN IF NOT EXISTS source VARCHAR(4) NOT NULL DEFAULT 'user'");
        $this->addSql('ALTER TABLE game_feedback DROP CONSTRAINT IF EXISTS chk_game_feedback_source');
        $this->addSql("ALTER TABLE game_feedback ADD CONSTRAINT chk_game_feedback_source CHECK (source IN ('user', 'auto'))");

        // Règle : le jeu a au moins une partie ; la note personnelle (sur 10) décide (≥ 7 aimé, < 5 bof), sinon les parties
        // (une partie notée 5 = aimée, 1 = pas aimée). Un choix manuel n'est jamais touché ; sans signal, la ligne auto disparaît.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION game_feedback_sync_auto(p_user integer, p_game integer) RETURNS void AS $$
            DECLARE
                v text := NULL;
                n double precision;
            BEGIN
                IF p_user IS NULL THEN
                    RETURN;
                END IF;
                IF EXISTS (SELECT 1 FROM game_feedback WHERE user_id = p_user AND game_id = p_game AND source = 'user') THEN
                    RETURN;
                END IF;

                SELECT bgg_user_rating INTO n FROM user_game WHERE user_id = p_user AND game_id = p_game;

                IF EXISTS (SELECT 1 FROM game_session WHERE user_id = p_user AND game_id = p_game) THEN
                    IF n IS NOT NULL AND n >= 7 THEN
                        v := 'played_liked';
                    ELSIF n IS NOT NULL AND n > 0 AND n < 5 THEN
                        v := 'played_meh';
                    ELSIF EXISTS (SELECT 1 FROM game_session WHERE user_id = p_user AND game_id = p_game AND rating = 5) THEN
                        v := 'played_liked';
                    ELSIF EXISTS (SELECT 1 FROM game_session WHERE user_id = p_user AND game_id = p_game AND rating = 1) THEN
                        v := 'played_meh';
                    END IF;
                END IF;

                IF v IS NULL THEN
                    DELETE FROM game_feedback WHERE user_id = p_user AND game_id = p_game AND source = 'auto';
                ELSE
                    INSERT INTO game_feedback (user_id, game_id, verdict, source) VALUES (p_user, p_game, v, 'auto')
                    ON CONFLICT (user_id, game_id) DO UPDATE SET verdict = EXCLUDED.verdict, created_at = NOW();
                END IF;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        // Une partie enregistrée : l'envie de jouer est satisfaite, puis le retour automatique se recalcule.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION game_session_clear_want_to_play() RETURNS trigger AS $$
            BEGIN
                IF NEW.user_id IS NOT NULL THEN
                    DELETE FROM game_feedback
                    WHERE user_id = NEW.user_id AND game_id = NEW.game_id AND verdict = 'want_to_play';
                    PERFORM game_feedback_sync_auto(NEW.user_id, NEW.game_id);
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION game_session_sync_auto() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    PERFORM game_feedback_sync_auto(OLD.user_id, OLD.game_id);
                    RETURN OLD;
                END IF;
                PERFORM game_feedback_sync_auto(NEW.user_id, NEW.game_id);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_sync_auto_update ON game_session');
        $this->addSql('CREATE TRIGGER trg_game_session_sync_auto_update AFTER UPDATE OF rating ON game_session FOR EACH ROW EXECUTE FUNCTION game_session_sync_auto()');
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_sync_auto_delete ON game_session');
        $this->addSql('CREATE TRIGGER trg_game_session_sync_auto_delete AFTER DELETE ON game_session FOR EACH ROW EXECUTE FUNCTION game_session_sync_auto()');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION user_game_sync_auto() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    PERFORM game_feedback_sync_auto(OLD.user_id, OLD.game_id);
                    RETURN OLD;
                END IF;
                PERFORM game_feedback_sync_auto(NEW.user_id, NEW.game_id);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS trg_user_game_sync_auto ON user_game');
        $this->addSql('CREATE TRIGGER trg_user_game_sync_auto AFTER INSERT OR UPDATE OF bgg_user_rating ON user_game FOR EACH ROW EXECUTE FUNCTION user_game_sync_auto()');
        $this->addSql('DROP TRIGGER IF EXISTS trg_user_game_sync_auto_delete ON user_game');
        $this->addSql('CREATE TRIGGER trg_user_game_sync_auto_delete AFTER DELETE ON user_game FOR EACH ROW EXECUTE FUNCTION user_game_sync_auto()');

        // Rattrapage des parties et des notes déjà enregistrées.
        $this->addSql('SELECT game_feedback_sync_auto(s.user_id, s.game_id) FROM (SELECT DISTINCT user_id, game_id FROM game_session WHERE user_id IS NOT NULL) s');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_user_game_sync_auto_delete ON user_game');
        $this->addSql('DROP TRIGGER IF EXISTS trg_user_game_sync_auto ON user_game');
        $this->addSql('DROP FUNCTION IF EXISTS user_game_sync_auto()');
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_sync_auto_delete ON game_session');
        $this->addSql('DROP TRIGGER IF EXISTS trg_game_session_sync_auto_update ON game_session');
        $this->addSql('DROP FUNCTION IF EXISTS game_session_sync_auto()');
        $this->addSql("
            CREATE OR REPLACE FUNCTION game_session_clear_want_to_play() RETURNS trigger AS \$\$
            BEGIN
                IF NEW.user_id IS NOT NULL THEN
                    DELETE FROM game_feedback WHERE user_id = NEW.user_id AND game_id = NEW.game_id AND verdict = 'want_to_play';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");
        $this->addSql('DROP FUNCTION IF EXISTS game_feedback_sync_auto(integer, integer)');
        $this->addSql("DELETE FROM game_feedback WHERE source = 'auto'");
        $this->addSql('ALTER TABLE game_feedback DROP CONSTRAINT IF EXISTS chk_game_feedback_source');
        $this->addSql('ALTER TABLE game_feedback DROP COLUMN IF EXISTS source');
    }
}
