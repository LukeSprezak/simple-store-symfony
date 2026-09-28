<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928153837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map domain Product directly: drop cross-context FK to user and unused sales_count';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY `FK_D34A04ADA76ED395`');
        $this->addSql('DROP INDEX IDX_D34A04ADA76ED395 ON product');
        $this->addSql('ALTER TABLE product DROP sales_count');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD sales_count INT NOT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT `FK_D34A04ADA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_D34A04ADA76ED395 ON product (user_id)');
    }
}
