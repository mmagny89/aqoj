<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bgg_rank, users_rated, is_expansion to game table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD COLUMN bgg_rank INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN users_rated INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD COLUMN is_expansion BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN bgg_rank');
        $this->addSql('ALTER TABLE game DROP COLUMN users_rated');
        $this->addSql('ALTER TABLE game DROP COLUMN is_expansion');
    }
}
