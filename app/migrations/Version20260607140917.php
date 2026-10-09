<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607140917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée user_preference ; ajoute name_original et bgg_type sur game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS user_preference (
                user_id          INTEGER       NOT NULL PRIMARY KEY,
                top_engines      JSON          NOT NULL DEFAULT '[]',
                top_support      JSON          NOT NULL DEFAULT '[]',
                ranked_families  JSON          NOT NULL DEFAULT '[]',
                top_categories   JSON          NOT NULL DEFAULT '[]',
                updated_at       TIMESTAMP(0) NOT NULL DEFAULT NOW(),
                CONSTRAINT fk_user_pref_user FOREIGN KEY (user_id)
                    REFERENCES app_user(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_preference');
    }
}
