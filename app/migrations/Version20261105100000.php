<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des tâches : demande d\'arrêt par un administrateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_run ADD COLUMN IF NOT EXISTS stop_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_run DROP COLUMN IF EXISTS stop_requested_at');
    }
}
