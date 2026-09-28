<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hash user tokens, remove persisted plaintext/repeated passwords and allow longer email addresses';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Keep the source columns until every token has been copied to its hash column.
        $this->addSql('ALTER TABLE user ADD token_hash VARCHAR(64) DEFAULT NULL, ADD reset_password_token_hash VARCHAR(64) DEFAULT NULL, MODIFY email VARCHAR(254) NOT NULL');
        $this->addSql('UPDATE user SET token_hash = CASE WHEN token IS NULL OR OCTET_LENGTH(token) = 0 THEN NULL ELSE SHA2(token, 256) END, reset_password_token_hash = CASE WHEN reset_password_token IS NULL OR OCTET_LENGTH(reset_password_token) = 0 THEN NULL ELSE SHA2(reset_password_token, 256) END');
        $this->addSql('ALTER TABLE user DROP token, DROP reset_password_token, DROP plain_password, DROP repeat_password');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Plaintext tokens and removed password columns cannot be restored from hashes.');
    }
}
