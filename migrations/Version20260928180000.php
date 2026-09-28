<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index carts by owner and status for the active cart creation limit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_cart_owner_status ON cart (owner_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cart_owner_status ON cart');
    }
}
