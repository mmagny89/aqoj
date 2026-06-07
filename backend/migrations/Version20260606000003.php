<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mechanic_families JSON column to game table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE game ADD COLUMN mechanic_families JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN mechanic_families');
    }
}
