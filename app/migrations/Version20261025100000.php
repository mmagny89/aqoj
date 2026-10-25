<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des tâches d\'import et de synchronisation BGG (table job_run)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS job_run (
                id BIGSERIAL PRIMARY KEY,
                command VARCHAR(100) NOT NULL,
                arguments VARCHAR(255) NOT NULL DEFAULT '',
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                exit_code SMALLINT DEFAULT NULL,
                metrics JSONB NOT NULL DEFAULT '{}'::jsonb,
                error TEXT DEFAULT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_job_run_command_started ON job_run (command, started_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_job_run_started ON job_run (started_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS job_run');
    }
}
