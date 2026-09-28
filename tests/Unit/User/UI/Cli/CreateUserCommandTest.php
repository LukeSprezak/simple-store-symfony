<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\UI\Cli;

use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\Entity\User;
use App\User\UI\Cli\CreateUserCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(CreateUserCommand::class)]
class CreateUserCommandTest extends TestCase
{
    private const string COMMAND_NAME = 'app:create-user';
    private const string VALID_EMAIL = 'luke@admin.com';
    private const string VALID_USERNAME = 'admin';
    private const string VALID_PASSWORD = 'Admin123';
    private const string SHORT_PASSWORD = 'short';
    private const string CUSTOM_ROLES = 'ROLE_ADMIN,ROLE_SUPER_ADMIN';
    private const string DEFAULT_ROLES = 'ROLE_USER';
    private const string INVALID_ROLES = 'ROLE_ADMIN,ROLE_INVALID';
    private const string DUPLICATE_ROLES = 'ROLE_ADMIN,ROLE_ADMIN,ROLE_USER';

    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ValidatorInterface&MockObject $validator;
    private Application $application;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $command = new CreateUserCommand($this->entityManager, $this->passwordHasher, $this->validator);
        $this->application = new Application();
        $this->application->addCommand($command);
    }

    #[Test]
    public function shouldSuccessfullyCreateUserWithDefaultValues(): void
    {
        $commandTester = $this->getCommandTester([
            '',
            '',
            self::VALID_PASSWORD,
            '',
        ]);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), self::VALID_PASSWORD)
            ->willReturn('hashed_Admin123');

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(User::class))
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $commandTester->execute([]);

        self::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        self::assertStringContainsString('The user has been successfully created!', $output);
        self::assertStringContainsString('ID: ', $output);
        self::assertStringContainsString('Email: '.self::VALID_EMAIL, $output);
        self::assertStringContainsString('Username: '.self::VALID_USERNAME, $output);
        self::assertStringContainsString('Roles: '.self::DEFAULT_ROLES, $output);
    }

    #[Test]
    public function shouldSuccessfullyCreateUserWithTheirOwnValues(): void
    {
        $commandTester = $this->getCommandTester([
            self::VALID_EMAIL,
            self::VALID_USERNAME,
            self::VALID_PASSWORD,
            self::CUSTOM_ROLES,
        ]);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), self::VALID_PASSWORD)
            ->willReturn('hashed_Luke1234');

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(User::class))
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $user) {
                return Uuid::isValid($user->getId())
                    && self::VALID_EMAIL === $user->getEmail()
                    && self::VALID_USERNAME === $user->getUsername()
                    && 'hashed_Luke1234' === $user->getPassword()
                    && 'hashed_Luke1234' === $user->getRepeatPassword()
                    && true === $user->getEnabled()
                    && $user->getRoles() === [
                        Role::ROLE_ADMIN->value,
                        Role::ROLE_SUPER_ADMIN->value,
                        Role::ROLE_USER->value,
                    ];
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $commandTester->execute([]);
        self::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The user has been successfully created!', $output);
        self::assertStringContainsString('ID: ', $output);
        self::assertStringContainsString('Email: '.self::VALID_EMAIL, $output);
        self::assertStringContainsString('Username: '.self::VALID_USERNAME, $output);
        self::assertStringContainsString('Roles: '.implode(', ', [
            Role::ROLE_ADMIN->value,
            Role::ROLE_SUPER_ADMIN->value,
            Role::ROLE_USER->value,
        ]), $output);
    }

    #[Test]
    public function shouldFailsWhenPasswordIsEmpty(): void
    {
        $commandTester = $this->getCommandTester([
            '',
            '',
            '',
            '',
        ]);

        $commandTester->execute([]);
        $this->assertFailureWithMessage($commandTester, 'Password cannot be empty.');
    }

    #[Test]
    public function shouldDisplayErrorIfPasswordIsTooShort(): void
    {
        $commandTester = $this->getCommandTester([
            '',
            '',
            self::SHORT_PASSWORD,
            '',
        ]);

        $commandTester->execute([]);
        $this->assertFailureWithMessage($commandTester, 'Password needs to be at least 8 characters long.');
    }

    #[Test]
    public function informsFailWhenValidatorFails(): void
    {
        $commandTester = $this->getCommandTester([
            '',
            '',
            self::VALID_PASSWORD,
            '',
        ]);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), self::VALID_PASSWORD)
            ->willReturn('hashed_password123');

        $mockConstraintViolation = $this->createMock(ConstraintViolationInterface::class);
        $mockConstraintViolation->method('getPropertyPath')->willReturn('email');
        $mockConstraintViolation->method('getMessage')->willReturn('This value is not a valid email.');
        $constraintViolationList = new ConstraintViolationList([$mockConstraintViolation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(User::class))
            ->willReturn($constraintViolationList);

        $commandTester->execute([]);
        $this->assertFailureWithMessage($commandTester, 'email: This value is not a valid email.');
    }

    #[Test]
    public function shouldFailIfIncorrectRolesAreSpecified(): void
    {
        $commandTester = $this->getCommandTester([
            self::VALID_EMAIL,
            self::VALID_USERNAME,
            self::VALID_PASSWORD,
            self::INVALID_ROLES,
        ]);

        $this->passwordHasher->expects($this->never())
            ->method('hashPassword');

        $this->validator->expects($this->never())
            ->method('validate');

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $commandTester->execute([]);

        $this->assertFailureWithMessage($commandTester, "Invalid role(s): 'ROLE_INVALID'. Allowed roles are: ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_USER.");
    }

    #[Test]
    public function shouldCreateUserWithUniqueRolesWhenYouEnterSameRoles(): void
    {
        $commandTester = $this->getCommandTester([
            self::VALID_EMAIL,
            self::VALID_USERNAME,
            self::VALID_PASSWORD,
            self::DUPLICATE_ROLES,
        ]);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), self::VALID_PASSWORD)
            ->willReturn('hashed_password');

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(User::class))
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $user) {
                return Uuid::isValid($user->getId())
                    && self::VALID_EMAIL === $user->getEmail()
                    && self::VALID_USERNAME === $user->getUsername()
                    && 'hashed_password' === $user->getPassword()
                    && 'hashed_password' === $user->getRepeatPassword()
                    && true === $user->getEnabled()
                    && $user->getRoles() === [
                        Role::ROLE_ADMIN->value,
                        Role::ROLE_USER->value,
                    ];
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $commandTester->execute([]);
        self::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The user has been successfully created!', $output);
        self::assertStringContainsString('ID: ', $output);
        self::assertStringContainsString('Email: '.self::VALID_EMAIL, $output);
        self::assertStringContainsString('Username: '.self::VALID_USERNAME, $output);
        self::assertStringContainsString('Roles: '.implode(', ', [
            Role::ROLE_ADMIN->value,
            Role::ROLE_USER->value,
        ]), $output);
    }

    private function getCommandTester(array $inputs = []): CommandTester
    {
        $commandTester = new CommandTester($this->application->find(self::COMMAND_NAME));
        $commandTester->setInputs($inputs);

        return $commandTester;
    }

    private function assertFailureWithMessage(CommandTester $commandTester, string $expectedMessage): void
    {
        self::assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $output = preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertStringContainsString($expectedMessage, $output);
    }
}
