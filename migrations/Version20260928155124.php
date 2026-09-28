<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928155124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map domain Order directly: product snapshot in order_item, drop cross-context FK to product and unused updated_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP updated_at');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY `FK_52EA1F094584665A`');
        $this->addSql('DROP INDEX IDX_52EA1F094584665A ON order_item');
        $this->addSql('ALTER TABLE order_item ADD product_name VARCHAR(255) NOT NULL, CHANGE unit_price product_price INT NOT NULL');
        $this->addSql('UPDATE order_item oi JOIN product p ON p.id = oi.product_id SET oi.product_name = p.name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP product_name, CHANGE product_price unit_price INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_52EA1F094584665A ON order_item (product_id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT `FK_52EA1F094584665A` FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE `order` ADD updated_at DATETIME DEFAULT NULL');
    }
}
