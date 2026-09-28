<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928151842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store product price in minor units (grosze)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE product SET price = ROUND(price * 100)');
        $this->addSql('ALTER TABLE product CHANGE price price INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product CHANGE price price DOUBLE PRECISION NOT NULL');
        $this->addSql('UPDATE product SET price = price / 100');
    }
}
