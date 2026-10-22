<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source and lastSyncedAt to game table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE game ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'bgg'");
        $this->addSql('ALTER TABLE game ADD COLUMN last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN source');
        $this->addSql('ALTER TABLE game DROP COLUMN last_synced_at');
    }
}
