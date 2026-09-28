<?php

declare(strict_types=1);

namespace App\Tests\Integration\User\Infrastructure\Doctrine;

use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    #[Test]
    public function persistsOnlyHashesAndKeepsTokensUsableAfterReload(): void
    {
        $user = $this->newUser()->setPlainPassword('transient-password')->setToken('activation-token');
        $user->setResetPasswordToken('reset-token');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM user WHERE id = :id', ['id' => $user->getId()]);
        self::assertIsArray($row);
        self::assertSame('password-hash', $row['password']);
        self::assertSame(hash('sha256', 'activation-token'), $row['token_hash']);
        self::assertSame(hash('sha256', 'reset-token'), $row['reset_password_token_hash']);
        foreach (['plain_password', 'repeat_password', 'token', 'reset_password_token'] as $column) {
            self::assertArrayNotHasKey($column, $row);
        }

        $id = $user->getId();
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getPlainPassword());
        self::assertTrue($reloaded->matchesToken('activation-token'));
        self::assertTrue($reloaded->matchesResetPasswordToken('reset-token'));

        $reloaded->setToken(null);
        $reloaded->setResetPasswordToken(null);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $cleared = $this->entityManager->find(User::class, $id);
        self::assertNotNull($cleared);
        self::assertFalse($cleared->matchesToken('activation-token'));
        self::assertFalse($cleared->matchesResetPasswordToken('reset-token'));
    }

    #[Test]
    public function acceptsAndPersistsAnEmailAtTheLengthLimit(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);
        $user = $this->newUser()->setEmail($email);
        self::assertCount(0, $this->validator->validate($user));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $id = $user->getId();
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $id);
        self::assertNotNull($reloaded);
        self::assertSame($email, $reloaded->getEmail());
    }

    #[Test]
    public function validatesUsernameAndEmailUniquenessIndependently(): void
    {
        $existing = $this->newUser();
        $this->entityManager->persist($existing);
        $this->entityManager->flush();

        $sameUsername = $this->newUser()->setUsername($existing->getUsername());
        $violations = $this->validator->validate($sameUsername);
        self::assertCount(1, $violations);
        self::assertSame('username', $violations->get(0)->getPropertyPath());

        $sameEmail = $this->newUser()->setEmail($existing->getEmail());
        $violations = $this->validator->validate($sameEmail);
        self::assertCount(1, $violations);
        self::assertSame('email', $violations->get(0)->getPropertyPath());

        self::assertCount(0, $this->validator->validate($existing));
        self::assertCount(0, $this->validator->validate($this->newUser()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid syntax' => ['invalid-email'];
        yield 'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 62)];
    }

    #[Test]
    #[DataProvider('invalidEmails')]
    public function rejectsInvalidEmailsBeforePersistence(string $email): void
    {
        $violations = $this->validator->validate($this->newUser()->setEmail($email));

        self::assertGreaterThan(0, $violations->count());
        foreach ($violations as $violation) {
            self::assertSame('email', $violation->getPropertyPath());
        }
    }

    #[Test]
    public function serializationDoesNotExposeCredentials(): void
    {
        $user = $this->newUser()->setPlainPassword('transient-password')->setToken('activation-token');
        $user->setResetPasswordToken('reset-token');
        $json = self::getContainer()->get(SerializerInterface::class)->serialize($user, 'json');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertSame($user->getEmail(), $data['email']);
        foreach (['password', 'plainPassword', 'tokenHash', 'resetPasswordTokenHash'] as $field) {
            self::assertArrayNotHasKey($field, $data);
        }
    }

    private function newUser(): User
    {
        $id = Uuid::v7()->toRfc4122();
        $name = str_replace('-', '', $id);

        return (new User())
            ->setId($id)
            ->setUsername($name)
            ->setEmail(substr($name, 0, 20).'@test.local')
            ->setPassword('password-hash')
            ->setEnabled(true);
    }
}
