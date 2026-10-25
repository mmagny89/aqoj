<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôle Membre ou VIP des comptes et codes d\'invitation pour l\'inscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS invitation_code (
                id SERIAL PRIMARY KEY,
                code VARCHAR(32) NOT NULL UNIQUE,
                label VARCHAR(100) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'member',
                max_uses INT NOT NULL,
                used_count INT NOT NULL DEFAULT 0,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_by_id INT DEFAULT NULL REFERENCES app_user (id) ON DELETE SET NULL
            )
            SQL);
        // Les comptes existants sont des membres : le défaut préserve leur accès.
        $this->addSql("ALTER TABLE app_user ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'member'");
        $this->addSql('ALTER TABLE app_user ADD COLUMN IF NOT EXISTS invitation_code_id INT DEFAULT NULL REFERENCES invitation_code (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_app_user_invitation_code ON app_user (invitation_code_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_app_user_invitation_code');
        $this->addSql('ALTER TABLE app_user DROP COLUMN IF EXISTS invitation_code_id');
        $this->addSql('ALTER TABLE app_user DROP COLUMN IF EXISTS role');
        $this->addSql('DROP TABLE IF EXISTS invitation_code');
    }
}
