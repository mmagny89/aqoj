<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index du classement BGG et du resynchronisation, mesurés : 22 ms → 0,2 ms et 21 ms → 0,5 ms sur 177 686 jeux';
    }

    public function up(Schema $schema): void
    {
        // Tri par rang (explorateur d'administration, classements) : évite de trier toute la table.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_game_bgg_rank ON game (bgg_rank ASC NULLS LAST, id)');
        // Resynchronisation : les fiches enrichies les plus anciennes d'abord, sans lire les fiches du CSV.
        $this->addSql("CREATE INDEX IF NOT EXISTS idx_game_resync ON game (last_synced_at ASC NULLS FIRST) WHERE source = 'bgg_api'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_game_resync');
        $this->addSql('DROP INDEX IF EXISTS idx_game_bgg_rank');
    }
}
