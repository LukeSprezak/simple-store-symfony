<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\UI\Controller;

use App\Order\Domain\Policy\CartLimits;
use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class CartLimitsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $token;
    private string $productId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
        $ownerId = UserId::generate();
        $uniqueName = str_replace('-', '', $ownerId->getId());
        $owner = new User()
            ->setId($ownerId->getId())
            ->setUsername($uniqueName)
            ->setEmail(substr($uniqueName, 0, 20).'@test.local')
            ->setPassword('unused')
            ->setEnabled(true);
        $this->productId = Uuid::v7()->toRfc4122();
        $product = Product::create($this->productId, 'Product', 'Description', new Money(1000), 100, $ownerId);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($owner);
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
    public function rejectsAnExcessiveRequestQuantityWith422(): void
    {
        $this->addProduct(CartLimits::MAX_QUANTITY_PER_PRODUCT + 1);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStock(100);
    }

    #[Test]
    public function rejectsAnAccumulatedQuantityAboveTheLimitWith409(): void
    {
        $this->addProduct(CartLimits::MAX_QUANTITY_PER_PRODUCT);
        self::assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('cartId', $data);
        self::assertIsString($data['cartId']);

        $this->addProduct(1, $data['cartId']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertStock(100 - CartLimits::MAX_QUANTITY_PER_PRODUCT);
    }

    #[Test]
    public function rejectsAnAdditionalActiveCartWith409(): void
    {
        for ($index = 0; $index < CartLimits::MAX_ACTIVE_CARTS_PER_OWNER; ++$index) {
            $this->addProduct(1);
            self::assertResponseIsSuccessful();
        }

        $this->addProduct(1);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertStock(100 - CartLimits::MAX_ACTIVE_CARTS_PER_OWNER);
    }

    private function addProduct(int $quantity, ?string $cartId = null): void
    {
        $this->client->jsonRequest('POST', '/api/cart/add-product'.(null === $cartId ? '' : '/'.$cartId), [
            'productId' => $this->productId,
            'quantity' => $quantity,
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
    }

    private function assertStock(int $quantity): void
    {
        $this->entityManager->clear();
        $product = $this->entityManager->find(Product::class, $this->productId);
        self::assertNotNull($product);
        self::assertSame($quantity, $product->getStockQuantity());
    }
}
