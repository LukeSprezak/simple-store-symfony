<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928114925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove DBAL 3 type comments and update the Doctrine Messenger queue index';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE cart_item CHANGE id id CHAR(36) NOT NULL, CHANGE cart_id cart_id CHAR(36) DEFAULT NULL, CHANGE product_id product_id CHAR(36) DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item CHANGE id id CHAR(36) NOT NULL, CHANGE order_id order_id CHAR(36) NOT NULL, CHANGE product_id product_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE product CHANGE id id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE review CHANGE id id CHAR(36) NOT NULL, CHANGE product_id product_id CHAR(36) NOT NULL, CHANGE user_id user_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL, CHANGE roles roles JSON NOT NULL, CHANGE last_password_change last_password_change DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_98B4978016BA31DB ON async_command_queue');
        $this->addSql('DROP INDEX IDX_98B49780E3BD61CE ON async_command_queue');
        $this->addSql('DROP INDEX IDX_98B49780FB7336F0 ON async_command_queue');
        $this->addSql('ALTER TABLE async_command_queue CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_98B49780FB7336F0E3BD61CE16BA31DBBF396750 ON async_command_queue (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_98B49780FB7336F0E3BD61CE16BA31DBBF396750 ON async_command_queue');
        $this->addSql('ALTER TABLE async_command_queue CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_98B4978016BA31DB ON async_command_queue (delivered_at)');
        $this->addSql('CREATE INDEX IDX_98B49780E3BD61CE ON async_command_queue (available_at)');
        $this->addSql('CREATE INDEX IDX_98B49780FB7336F0 ON async_command_queue (queue_name)');
        $this->addSql('ALTER TABLE cart CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cart_item CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE deleted_at deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE cart_id cart_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE product_id product_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE `order` CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE order_item CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE order_id order_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE product_id product_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE product CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE review CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE product_id product_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE last_password_change last_password_change DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
