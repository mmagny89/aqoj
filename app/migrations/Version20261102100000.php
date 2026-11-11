<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Édition française d\'un jeu : éditeur, année et image de la boîte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS edition_fr_publisher VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS edition_fr_year INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS edition_fr_image_url VARCHAR(500) DEFAULT NULL');

        // Les fiches qui ont déjà un titre français ont une édition française : on les rend au resynchroniseur,
        // qui sert d'abord les fiches jamais synchronisées, pour qu'elles se remplissent en quelques nuits.
        $this->addSql("UPDATE game SET last_synced_at = NULL WHERE source = 'bgg_api' AND name_original IS NOT NULL AND name <> name_original");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS edition_fr_image_url');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS edition_fr_year');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS edition_fr_publisher');
    }
}
