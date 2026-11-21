<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filtres de contexte : nombres de joueurs idéaux et conseillés (sondage BGG), remplis par le resynchroniseur';
    }

    public function up(Schema $schema): void
    {
        // null = sondage absent ou trop peu voté : le jeu reste visible, étiqueté « inconnu ».
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS best_players JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS recommended_players JSON DEFAULT NULL');

        // Les fiches déjà enrichies n'ont pas le sondage : rendues au resynchroniseur planifié (aucun appel BGG ici).
        $this->addSql("UPDATE game SET last_synced_at = NULL WHERE source = 'bgg_api' AND is_expansion = FALSE AND best_players IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS recommended_players');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS best_players');
    }
}
