<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929073705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when a contact message was read in the staff panel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message ADD read_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP read_at');
    }
}
