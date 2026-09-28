<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928120914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cart owner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart ADD owner_id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP owner_id');
    }
}
