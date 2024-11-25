<?php

declare(strict_types=1);

namespace App\User\UI\Cli;

use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use LengthException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $io->ask('Enter email:', 'luke@admin.com');
        $username = $io->ask('Enter username:', 'admin');

        $password = $io->askHidden('Enter password:', function (?string $password) {
            if (null === $password || '' === trim($password)) {
                throw new InvalidArgumentException('Password cannot be empty.');
            }

            if (8 > mb_strlen($password)) {
                throw new LengthException('Password needs to be at least 8 characters long.');
            }

            return $password;
        });

        $roles = $io->ask(
            'Specify the user roles (separated by commas, e.g. ROLE_USER,ROLE_ADMIN):',
            Role::ROLE_USER->value,
            function (?string $input) {
                if (empty($input)) {
                    return [Role::ROLE_USER->value];
                }

                $rolesArray = array_map('trim', explode(',', $input));

                $allowedRoles = array_map(static fn($role) => $role->value, Role::cases());

                foreach ($rolesArray as $role) {
                    if (! in_array($role, $allowedRoles, true)) {
                        throw new InvalidArgumentException(
                            sprintf(
                                "Invalid role: '%s'. Allowed roles are: %s.",
                                $role,
                                implode(', ', $allowedRoles)
                            )
                        );
                    }
                }

                return array_unique($rolesArray);
            }
        );

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

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath() . ': ' . $error->getMessage());
            }

            return Command::FAILURE;
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('The user has been successfully created!');
            $io->text('ID: ' . $user->getId());
            $io->text('Email: ' . $user->getEmail());
            $io->text('Username: ' . $user->getUsername());
            $io->text('Role: ' . implode(', ', $user->getRoles()));
        } catch (Exception $exception) {
            $io->error('User not created');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
