<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DataFixtures;

use App\Product\Domain\Model\Product;
use App\Product\Infrastructure\Doctrine\Entity\Category;
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
    /**
     * Category => [icon, subcategory => list of [name, description, price in cents, stock]].
     */
    private const array CATALOGUE = [
        'Peripherals' => ['keyboard', [
            'Keyboards' => [
                ['Keyboard', 'Mechanical keyboard', 34999, 50],
                ['Keyboard Wrist Rest', 'Memory foam wrist rest', 6999, 75],
            ],
            'Mice' => [
                ['Mouse', 'Wireless mouse', 12999, 100],
                ['Mouse Pad', 'Extended desk mouse pad', 7999, 120],
            ],
            'Webcams & Streaming' => [
                ['Webcam', 'Full HD webcam', 24999, 0],
                ['Microphone', 'USB condenser microphone', 39999, 15],
                ['Webcam Light', 'Clip-on ring light', 8999, 65],
                ['Streaming Deck', 'Programmable stream controller', 69999, 13],
            ],
        ]],
        'Displays' => ['desktop_windows', [
            'Monitors' => [
                ['Monitor', '27-inch 4K monitor', 189999, 20],
                ['Portable Monitor', '15.6-inch USB-C monitor', 99999, 14],
                ['Drawing Monitor', '16-inch pen display', 179999, 6],
            ],
            'Stands & Mounts' => [
                ['Laptop Stand', 'Adjustable aluminium laptop stand', 14999, 40],
                ['Monitor Arm', 'Gas-spring dual monitor arm', 39999, 20],
            ],
        ]],
        'Audio' => ['headphones', [
            'Headphones' => [
                ['Headphones', 'Noise-cancelling headphones', 79999, 30],
                ['Earbuds', 'True wireless earbuds', 44999, 55],
            ],
            'Speakers' => [
                ['Bluetooth Speaker', 'Portable waterproof speaker', 29999, 45],
                ['Noise Machine', 'White noise sleep machine', 16999, 0],
            ],
        ]],
        'Storage & Networking' => ['storage', [
            'Storage' => [
                ['External SSD', '1 TB portable NVMe SSD', 44999, 25],
                ['NAS', '2-bay network storage', 109999, 11],
                ['Memory Card', '256 GB microSD card', 12999, 85],
            ],
            'Networking' => [
                ['Router', 'Wi-Fi 6 dual-band router', 49999, 18],
            ],
        ]],
        'Cables & Power' => ['cable', [
            'Hubs & Cables' => [
                ['USB-C Hub', '7-in-1 USB-C hub with HDMI', 19999, 60],
                ['Docking Station', 'Thunderbolt 4 docking station', 139999, 10],
                ['HDMI Cable', '2 m HDMI 2.1 cable', 3999, 200],
                ['Cable Organizer', 'Under-desk cable tray', 5999, 90],
            ],
            'Power' => [
                ['Wireless Charger', '15 W Qi wireless charger', 9999, 80],
                ['Power Bank', '20000 mAh power bank', 15999, 70],
                ['Surge Protector', '8-outlet surge protector', 11999, 50],
                ['Smart Plug', 'Wi-Fi smart plug with energy meter', 4999, 150],
            ],
        ]],
        'Devices & Office' => ['devices', [
            'Mobile & Wearables' => [
                ['Smartwatch', 'Fitness smartwatch with GPS', 99999, 22],
                ['E-reader', '6-inch e-ink reader', 59999, 16],
                ['Tablet', '11-inch Android tablet', 149999, 0],
                ['Graphics Tablet', 'Pen tablet with 8192 pressure levels', 54999, 12],
                ['VR Headset', 'Standalone VR headset', 219999, 4],
                ['Action Camera', '4K action camera', 119999, 0],
            ],
            'Office' => [
                ['Desk Lamp', 'LED desk lamp with dimmer', 17999, 35],
                ['Printer', 'Colour laser printer', 89999, 9],
                ['Scanner', 'Document scanner with ADF', 69999, 7],
                ['Standing Desk', 'Electric height-adjustable desk', 249999, 5],
                ['Gaming Chair', 'Ergonomic gaming chair', 129999, 8],
            ],
        ]],
    ];

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
        $position = 0;
        foreach (self::CATALOGUE as $categoryName => [$icon, $subcategories]) {
            $category = new Category(Uuid::v7()->toRfc4122(), null, $categoryName, $this->slug($categoryName), $icon, $position++);
            $manager->persist($category);

            foreach ($subcategories as $subcategoryName => $products) {
                $subcategory = new Category(Uuid::v7()->toRfc4122(), $category->getId(), $subcategoryName, $this->slug($subcategoryName), null, $position++);
                $manager->persist($subcategory);

                foreach ($products as [$name, $description, $price, $stock]) {
                    $manager->persist(Product::create(Uuid::v7()->toRfc4122(), $name, $description, new Money($price), $stock, $adminId, $subcategory->getId()));
                }
            }
        }

        $manager->flush();
    }

    private function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
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
