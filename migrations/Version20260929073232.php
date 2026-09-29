<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929073232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact_message for messages sent through the shop contact form';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (id CHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(254) NOT NULL, subject VARCHAR(150) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_message');
    }
}
