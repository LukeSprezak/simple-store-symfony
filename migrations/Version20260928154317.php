<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928154317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map domain Cart directly: product snapshot in cart_item, drop cross-context FK to product and deleted flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY `FK_F0FE25274584665A`');
        $this->addSql('DROP INDEX IDX_F0FE25274584665A ON cart_item');
        $this->addSql('ALTER TABLE cart_item ADD product_name VARCHAR(255) NOT NULL, ADD product_price INT NOT NULL');
        $this->addSql('UPDATE cart_item ci JOIN product p ON p.id = ci.product_id SET ci.product_name = p.name, ci.product_price = p.price');
        $this->addSql('ALTER TABLE cart_item DROP deleted, CHANGE cart_id cart_id CHAR(36) NOT NULL, CHANGE product_id product_id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item ADD deleted TINYINT(1) NOT NULL, CHANGE cart_id cart_id CHAR(36) DEFAULT NULL, CHANGE product_id product_id CHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE cart_item SET deleted = deleted_at IS NOT NULL');
        $this->addSql('ALTER TABLE cart_item DROP product_name, DROP product_price');
        $this->addSql('CREATE INDEX IDX_F0FE25274584665A ON cart_item (product_id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT `FK_F0FE25274584665A` FOREIGN KEY (product_id) REFERENCES product (id)');
    }
}
