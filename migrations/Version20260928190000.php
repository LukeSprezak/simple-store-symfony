<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the transactional domain event outbox and idempotent cart activity projection';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE domain_event_outbox (id CHAR(36) NOT NULL, event_name VARCHAR(100) NOT NULL, payload LONGTEXT NOT NULL, recorded_at DATETIME NOT NULL, schema_version INT NOT NULL, published_at DATETIME DEFAULT NULL, attempts INT NOT NULL, available_at DATETIME NOT NULL, last_error VARCHAR(255) DEFAULT NULL, INDEX idx_outbox_pending (published_at, available_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cart_activity (event_id CHAR(36) NOT NULL, cart_id CHAR(36) NOT NULL, event_name VARCHAR(100) NOT NULL, recorded_at DATETIME NOT NULL, product_id CHAR(36) DEFAULT NULL, quantity INT DEFAULT NULL, INDEX idx_cart_activity_page (cart_id, event_id), PRIMARY KEY(event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropping the outbox would discard pending events and the recorded cart history.');
    }
}
