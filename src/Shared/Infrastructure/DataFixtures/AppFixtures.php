<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DataFixtures;

use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\Enum\Role;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser('admin', 'admin@example.com', [Role::ROLE_ADMIN->value]);
        $user = $this->createUser('user', 'user@example.com', [Role::ROLE_USER->value]);
        $manager->persist($admin);
        $manager->persist($user);

        $adminId = new UserId($admin->getId());
        foreach ([
            ['Keyboard', 'Mechanical keyboard', 34999, 50],
            ['Mouse', 'Wireless mouse', 12999, 100],
            ['Monitor', '27-inch 4K monitor', 189999, 20],
            ['Headphones', 'Noise-cancelling headphones', 79999, 30],
            ['Webcam', 'Full HD webcam', 24999, 0],
        ] as [$name, $description, $price, $stock]) {
            $manager->persist(Product::create(Uuid::v7()->toRfc4122(), $name, $description, new Money($price), $stock, $adminId));
        }

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, string $email, array $roles): User
    {
        $user = new User()
            ->setId(Uuid::v7()->toRfc4122())
            ->setUsername($username)
            ->setEmail($email)
            ->setRoles($roles)
            ->setEnabled(true);

        return $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
    }
}
