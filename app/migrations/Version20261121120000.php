<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retours sur les propositions : ajoute « j\'aimerais jouer » et « j\'ai joué, et j\'aime »';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_feedback DROP CONSTRAINT IF EXISTS chk_game_feedback_verdict');
        $this->addSql("ALTER TABLE game_feedback ADD CONSTRAINT chk_game_feedback_verdict CHECK (verdict IN ('not_interested', 'played_meh', 'want_to_play', 'played_liked'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM game_feedback WHERE verdict IN ('want_to_play', 'played_liked')");
        $this->addSql('ALTER TABLE game_feedback DROP CONSTRAINT IF EXISTS chk_game_feedback_verdict');
        $this->addSql("ALTER TABLE game_feedback ADD CONSTRAINT chk_game_feedback_verdict CHECK (verdict IN ('not_interested', 'played_meh'))");
    }
}
