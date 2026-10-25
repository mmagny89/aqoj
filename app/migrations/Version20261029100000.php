<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Confirmation de l\'adresse e-mail : jetons à usage multiple (mot de passe oublié, confirmation) et date de confirmation';
    }

    public function up(Schema $schema): void
    {
        // Une seule table de jetons, une finalité par ligne : mot de passe oublié et confirmation d'adresse.
        $this->addSql('ALTER TABLE IF EXISTS password_reset_request RENAME TO user_token');
        $this->addSql("ALTER TABLE user_token ADD COLUMN IF NOT EXISTS purpose VARCHAR(30) NOT NULL DEFAULT 'password_reset'");
        $this->addSql('ALTER INDEX IF EXISTS idx_password_reset_user RENAME TO idx_user_token_user');

        $this->addSql('ALTER TABLE app_user ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Les comptes existants ont déjà servi : ils sont réputés confirmés, sinon ils perdraient leur accès.
        $this->addSql('UPDATE app_user SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP COLUMN IF EXISTS email_verified_at');
        $this->addSql("DELETE FROM user_token WHERE purpose <> 'password_reset'");
        $this->addSql('ALTER TABLE user_token DROP COLUMN IF EXISTS purpose');
        $this->addSql('ALTER INDEX IF EXISTS idx_user_token_user RENAME TO idx_password_reset_user');
        $this->addSql('ALTER TABLE IF EXISTS user_token RENAME TO password_reset_request');
    }
}
