<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove is_central_capable from mechanic_mapping — engine detection now uses ENGINE_FAMILIES constant in code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mechanic_mapping DROP COLUMN is_central_capable');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mechanic_mapping ADD COLUMN is_central_capable BOOLEAN NOT NULL DEFAULT FALSE');
    }
}
