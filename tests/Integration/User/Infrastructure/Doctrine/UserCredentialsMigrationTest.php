<?php

declare(strict_types=1);

namespace App\Tests\Integration\User\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-import-type Params from DriverManager
 */
final class UserCredentialsMigrationTest extends KernelTestCase
{
    #[Test]
    public function migratesExistingCredentialsWithoutKeepingPlaintext(): void
    {
        self::bootKernel();
        /** @var Params $parameters */
        $parameters = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams();
        $connection = DriverManager::getConnection($parameters);

        try {
            // This connection-local table shadows the real user table, which is never modified.
            $connection->executeStatement('CREATE TEMPORARY TABLE user (id CHAR(36) NOT NULL PRIMARY KEY, email VARCHAR(32) NOT NULL, password VARCHAR(255) NOT NULL, repeat_password VARCHAR(255) NOT NULL, plain_password VARCHAR(255) DEFAULT NULL, token VARCHAR(255) DEFAULT NULL, reset_password_token VARCHAR(255) DEFAULT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $tokens = [str_repeat('a', 255), 'zażółć-token', null, '', ' ', '0', str_repeat('f', 64)];
            $ids = [];
            foreach ($tokens as $index => $token) {
                $id = Uuid::v7()->toRfc4122();
                $ids[] = $id;
                $connection->insert('user', [
                    'id' => $id,
                    'email' => 'user'.$index.'@test.local',
                    'password' => 'existing-password-hash',
                    'repeat_password' => 'redundant-password-hash',
                    'plain_password' => 'old-plaintext-password',
                    'token' => $token,
                    'reset_password_token' => $token,
                ]);
            }

            $migration = $this->migration();
            self::assertFalse($migration->isTransactional());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }

            foreach ($tokens as $index => $token) {
                $row = $connection->fetchAssociative('SELECT * FROM user WHERE id = ?', [$ids[$index]]);
                self::assertIsArray($row);
                $expectedHash = null === $token || '' === $token ? null : hash('sha256', $token);
                self::assertSame($expectedHash, $row['token_hash']);
                self::assertSame($expectedHash, $row['reset_password_token_hash']);
                self::assertSame('existing-password-hash', $row['password']);
                self::assertSame('user'.$index.'@test.local', $row['email']);
                foreach (['token', 'reset_password_token', 'plain_password', 'repeat_password'] as $column) {
                    self::assertArrayNotHasKey($column, $row);
                }
            }

            $longEmail = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);
            $connection->update('user', ['email' => $longEmail], ['id' => $ids[0]]);
            self::assertSame($longEmail, $connection->fetchOne('SELECT email FROM user WHERE id = ?', [$ids[0]]));
        } finally {
            $connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS user');
            $connection->close();
        }
    }

    #[Test]
    public function refusesToPretendThatPlaintextCanBeRestored(): void
    {
        self::bootKernel();
        $this->expectException(IrreversibleMigration::class);

        $this->migration()->down(new Schema());
    }

    private function migration(): AbstractMigration
    {
        $factory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $factory);

        return $factory->getMigrationRepository()->getMigrations()
            ->getMigration(new Version('DoctrineMigrations\\Version20260928183000'))->getMigration();
    }
}
