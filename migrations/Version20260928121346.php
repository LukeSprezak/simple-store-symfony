<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928121346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order owner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD owner_id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP owner_id');
    }
}
