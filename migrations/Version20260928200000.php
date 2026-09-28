<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the idempotent order status history projection';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_status_history (event_id CHAR(36) NOT NULL, order_id CHAR(36) NOT NULL, transition VARCHAR(50) NOT NULL, from_status VARCHAR(50) NOT NULL, to_status VARCHAR(50) NOT NULL, recorded_at DATETIME NOT NULL, INDEX idx_order_status_history_page (order_id, event_id), PRIMARY KEY(event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropping the projection would discard the recorded order status history.');
    }
}
