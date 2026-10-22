<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bgg_user_rating to user_game join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_game ADD COLUMN bgg_user_rating FLOAT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_game DROP COLUMN bgg_user_rating');
    }
}
