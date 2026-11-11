<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Éditeur de l\'édition d\'origine d\'un jeu (l\'année d\'origine est year_published)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN IF NOT EXISTS publisher_original VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS publisher_original');
    }
}
