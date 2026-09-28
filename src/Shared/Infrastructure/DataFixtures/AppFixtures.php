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
            ['Laptop Stand', 'Adjustable aluminium laptop stand', 14999, 40],
            ['USB-C Hub', '7-in-1 USB-C hub with HDMI', 19999, 60],
            ['External SSD', '1 TB portable NVMe SSD', 44999, 25],
            ['Microphone', 'USB condenser microphone', 39999, 15],
            ['Desk Lamp', 'LED desk lamp with dimmer', 17999, 35],
            ['Mouse Pad', 'Extended desk mouse pad', 7999, 120],
            ['Wireless Charger', '15 W Qi wireless charger', 9999, 80],
            ['Bluetooth Speaker', 'Portable waterproof speaker', 29999, 45],
            ['Graphics Tablet', 'Pen tablet with 8192 pressure levels', 54999, 12],
            ['Router', 'Wi-Fi 6 dual-band router', 49999, 18],
            ['Smartwatch', 'Fitness smartwatch with GPS', 99999, 22],
            ['E-reader', '6-inch e-ink reader', 59999, 16],
            ['Power Bank', '20000 mAh power bank', 15999, 70],
            ['Gaming Chair', 'Ergonomic gaming chair', 129999, 8],
            ['Standing Desk', 'Electric height-adjustable desk', 249999, 5],
            ['Docking Station', 'Thunderbolt 4 docking station', 139999, 10],
            ['Earbuds', 'True wireless earbuds', 44999, 55],
            ['Action Camera', '4K action camera', 119999, 0],
            ['Drawing Monitor', '16-inch pen display', 179999, 6],
            ['Printer', 'Colour laser printer', 89999, 9],
            ['Scanner', 'Document scanner with ADF', 69999, 7],
            ['NAS', '2-bay network storage', 109999, 11],
            ['Portable Monitor', '15.6-inch USB-C monitor', 99999, 14],
            ['Cable Organizer', 'Under-desk cable tray', 5999, 90],
            ['Webcam Light', 'Clip-on ring light', 8999, 65],
            ['Streaming Deck', 'Programmable stream controller', 69999, 13],
            ['Smart Plug', 'Wi-Fi smart plug with energy meter', 4999, 150],
            ['VR Headset', 'Standalone VR headset', 219999, 4],
            ['Keyboard Wrist Rest', 'Memory foam wrist rest', 6999, 75],
            ['Monitor Arm', 'Gas-spring dual monitor arm', 39999, 20],
            ['Tablet', '11-inch Android tablet', 149999, 0],
            ['Surge Protector', '8-outlet surge protector', 11999, 50],
            ['HDMI Cable', '2 m HDMI 2.1 cable', 3999, 200],
            ['Memory Card', '256 GB microSD card', 12999, 85],
            ['Noise Machine', 'White noise sleep machine', 16999, 0],
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
