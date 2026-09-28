<?php

declare(strict_types=1);

namespace App\User\UI\Cli;

use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a user',
    aliases: ['app:add-user'],
    hidden: false
)]
class CreateUserCommand extends Command
{
    private const string DEFAULT_EMAIL = 'luke@admin.com';
    private const string DEFAULT_USERNAME = 'admin';
    private const array DEFAULT_ROLES = [Role::ROLE_USER->value];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $email = $this->askEmail($io);
            $username = $this->askUsername($io);
            $password = $this->askPassword($io);
            $roles = $this->askRoles($io);

            $user = $this->createUser($email, $username, $password, $roles);
            $this->validateUser($user, $io);
            $this->saveUser($user, $io);

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function askEmail(SymfonyStyle $io): string
    {
        return $io->ask('Enter email:', self::DEFAULT_EMAIL);
    }

    private function askUsername(SymfonyStyle $io): string
    {
        return $io->ask('Enter username:', self::DEFAULT_USERNAME);
    }

    private function askPassword(SymfonyStyle $io): string
    {
        $question = new Question('Enter password:');
        $question->setHidden(true);
        $question->setMaxAttempts(3);
        $question->setValidator(function (?string $password) {
            if (null === $password || '' === trim($password)) {
                throw new \InvalidArgumentException('Password cannot be empty.');
            }

            if (8 > mb_strlen($password)) {
                throw new \LengthException('Password needs to be at least 8 characters long.');
            }

            return $password;
        });

        return $io->askQuestion($question);
    }

    private function askRoles(SymfonyStyle $io): array
    {
        $allowedRoles = array_map(static fn (Role $role) => $role->value, Role::cases());
        $input = $io->ask(
            'Specify the user roles (separated by commas, e.g. ROLE_USER,ROLE_ADMIN):',
            implode(',', self::DEFAULT_ROLES)
        );

        if (empty(trim($input))) {
            return self::DEFAULT_ROLES;
        }

        $rolesArray = array_unique(array_map('trim', explode(',', $input)));
        $invalidRoles = array_diff($rolesArray, $allowedRoles);

        if (!empty($invalidRoles)) {
            throw new \InvalidArgumentException(sprintf("Invalid role(s): '%s'. Allowed roles are: %s.", implode(', ', $invalidRoles), implode(', ', $allowedRoles)));
        }

        return array_values($rolesArray);
    }

    private function createUser(
        string $email,
        string $username,
        string $password,
        array $roles,
    ): User {
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user
            ->setId(Uuid::v7()->toRfc4122())
            ->setUsername($username)
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword($hashedPassword)
            ->setRepeatPassword($hashedPassword)
            ->setEnabled(true);

        return $user;
    }

    private function validateUser(User $user, SymfonyStyle $io): void
    {
        $errors = $this->validator->validate($user);
        if (0 < count($errors)) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath().': '.$error->getMessage());
            }

            throw new \InvalidArgumentException('User validation failed.');
        }
    }

    private function saveUser(User $user, SymfonyStyle $io): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('The user has been successfully created!');
            $io->text('ID: '.$user->getId());
            $io->text('Email: '.$user->getEmail());
            $io->text('Username: '.$user->getUsername());
            $io->text('Roles: '.implode(', ', $user->getRoles()));
        } catch (\Exception $exception) {
            $io->error('User not created: '.$exception->getMessage());

            throw new \RuntimeException('User creation failed.');
        }
    }
}
