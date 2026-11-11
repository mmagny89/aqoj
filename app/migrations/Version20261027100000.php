<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mot de passe oublié (jetons de réinitialisation), date du dernier changement de mot de passe et numéro d\'adhérent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_request (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES app_user (id) ON DELETE CASCADE,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_password_reset_user ON password_reset_request (user_id)');
        $this->addSql('ALTER TABLE app_user ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD COLUMN IF NOT EXISTS member_number VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP COLUMN IF EXISTS member_number');
        $this->addSql('ALTER TABLE app_user DROP COLUMN IF EXISTS password_changed_at');
        $this->addSql('DROP INDEX IF EXISTS idx_password_reset_user');
        $this->addSql('DROP TABLE IF EXISTS password_reset_request');
    }
}
