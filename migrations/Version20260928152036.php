<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928152036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Freeze unit price in order items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item ADD unit_price INT NOT NULL');
        $this->addSql('UPDATE order_item oi JOIN product p ON p.id = oi.product_id SET oi.unit_price = p.price');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP unit_price');
    }
}
