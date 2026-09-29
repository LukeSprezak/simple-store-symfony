<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929075028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-level product categories; existing products stay uncategorised';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id CHAR(36) NOT NULL, parent_id CHAR(36) DEFAULT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, icon VARCHAR(50) DEFAULT NULL, position INT NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX idx_category_parent (parent_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE product ADD category_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_product_category ON product (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_category ON product');
        $this->addSql('ALTER TABLE product DROP category_id');
        $this->addSql('DROP TABLE category');
    }
}
