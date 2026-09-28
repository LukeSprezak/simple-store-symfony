<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\UI\Controller;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Event\CartConverted;
use App\Order\Domain\Event\CartCreated;
use App\Order\Domain\Event\ProductAddedToCart;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\ProductSnapshot;
use App\Product\Domain\Model\Product;
use App\Shared\Application\Bus\Event\PublishedEvent;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ReadEndpointsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $owner;
    private string $token;
    private string $productId;
    private string $cartId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
        $id = Uuid::v7()->toRfc4122();
        $name = str_replace('-', '', $id);
        $this->owner = new User()->setId($id)->setUsername($name)->setEmail(substr($name, 0, 20).'@test.local')->setPassword('unused')->setEnabled(true);
        $this->productId = Uuid::v7()->toRfc4122();
        $product = Product::create($this->productId, 'Product', 'Description', new Money(1234), 10, new UserId($id));
        $this->cartId = Uuid::v4()->toRfc4122();
        $cart = Cart::create($this->cartId, StatusCart::ACTIVE, new UserId($id));
        $cart->addProduct(new ProductSnapshot($this->productId, 'Original name', new Money(1000)), 2);
        $this->entityManager->persist($this->owner);
        $this->entityManager->persist($product);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
        $this->token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($this->owner);
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
    public function readsTheOwnersCartUsingSnapshotValuesAndIntegerMoney(): void
    {
        $this->request('GET', '/api/cart/'.$this->cartId);

        self::assertResponseIsSuccessful();
        $body = $this->body();
        self::assertSame($this->cartId, $body['id']);
        self::assertSame('active', $body['status']);
        self::assertSame(2000, $body['totalAmountInCents']);
        self::assertIsString($body['createdAt']);
        self::assertIsString($body['expiresAt']);
        self::assertIsArray($body['items']);
        self::assertCount(1, $body['items']);
        $item = $body['items'][0];
        self::assertIsArray($item);
        self::assertSame($this->productId, $item['productId']);
        self::assertSame('Original name', $item['productName']);
        self::assertSame(1000, $item['unitPriceInCents']);
        self::assertSame(2, $item['quantity']);
        self::assertSame(2000, $item['totalAmountInCents']);
        self::assertArrayNotHasKey('ownerId', $body);
    }

    #[Test]
    public function readsAnActiveProductAsRoleUserWithoutEnablingProductWrites(): void
    {
        $this->request('GET', '/api/product/'.$this->productId);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id' => $this->productId,
            'name' => 'Product',
            'description' => 'Description',
            'priceInCents' => 1234,
            'stockQuantity' => 10,
        ], $this->body());

        $this->client->jsonRequest('POST', '/api/product/add', [
            'name' => 'New product', 'description' => 'Description', 'price' => 12.34, 'stockQuantity' => 10,
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        self::assertResponseStatusCodeSame(403);

        $this->request('DELETE', '/api/product/remove/'.$this->productId);
        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function returnsTheSame404ForForeignAndUnknownCarts(): void
    {
        $this->entityManager->getConnection()->update('cart', ['owner_id' => UserId::generate()->getId()], ['id' => $this->cartId]);
        $this->request('GET', '/api/cart/'.$this->cartId);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => sprintf('Cart with ID "%s" does not exist.', $this->cartId)], $this->body());

        $missingId = Uuid::v7()->toRfc4122();
        $this->request('GET', '/api/cart/'.$missingId);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => sprintf('Cart with ID "%s" does not exist.', $missingId)], $this->body());
    }

    #[Test]
    public function returns404ForInactiveAndUnknownProducts(): void
    {
        $this->entityManager->getConnection()->update('product', ['status' => 'inactive'], ['id' => $this->productId]);
        $this->request('GET', '/api/product/'.$this->productId);
        self::assertResponseStatusCodeSame(404);

        $this->request('GET', '/api/product/'.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function bothReadEndpointsRequireAuthentication(): void
    {
        $this->client->jsonRequest('GET', '/api/cart/'.$this->cartId);
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('GET', '/api/product/'.$this->productId);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function disabledAccountsCannotReadWithAnExistingJwt(): void
    {
        $this->owner->setEnabled(false);
        $this->entityManager->flush();

        $this->request('GET', '/api/cart/'.$this->cartId);
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedRouteIds(): iterable
    {
        yield 'read cart' => ['GET', '/api/cart/not-a-uuid'];
        yield 'read product' => ['GET', '/api/product/not-a-uuid'];
        yield 'convert cart' => ['POST', '/api/cart/not-a-uuid/convert'];
        yield 'add to cart' => ['POST', '/api/cart/add-product/not-a-uuid'];
        yield 'remove product' => ['DELETE', '/api/product/remove/not-a-uuid'];
    }

    #[Test]
    #[DataProvider('malformedRouteIds')]
    public function rejectsMalformedIdsInTheRouter(string $method, string $path): void
    {
        $this->request($method, $path);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('active', $this->entityManager->getConnection()->fetchOne('SELECT status FROM cart WHERE id = ?', [$this->cartId]));
        self::assertSame(10, $this->entityManager->getConnection()->fetchOne('SELECT stock_quantity FROM product WHERE id = ?', [$this->productId]));
    }

    #[Test]
    public function uuidV7CartIdsWorkAlongsideUuidV4(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $cart = Cart::create($id, StatusCart::ACTIVE, new UserId($this->owner->getId()));
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        $this->request('GET', '/api/cart/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame($id, $this->body()['id']);
    }

    #[Test]
    public function apiRunsWithoutSessionServicesOrCookies(): void
    {
        $this->request('GET', '/api/cart/'.$this->cartId);

        self::assertResponseIsSuccessful();
        self::assertNotContains('session.factory', self::getContainer()->getServiceIds());
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    #[Test]
    public function cartActivityIsProjectedIdempotentlyAndPaginatedInRecordedOrder(): void
    {
        $path = '/api/cart/'.$this->cartId.'/activity';
        $this->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame(['items' => [], 'nextCursor' => null], $this->body());
        $events = [
            new PublishedEvent(Uuid::v7()->toRfc4122(), CartCreated::NAME, '2026-01-01T00:00:00+00:00', json_encode(['cartId' => $this->cartId, 'ownerId' => $this->owner->getId()], JSON_THROW_ON_ERROR)),
            new PublishedEvent(Uuid::v7()->toRfc4122(), ProductAddedToCart::NAME, '2026-01-01T00:00:01+00:00', json_encode(['cartId' => $this->cartId, 'productId' => $this->productId, 'quantity' => 2], JSON_THROW_ON_ERROR)),
            new PublishedEvent(Uuid::v7()->toRfc4122(), CartConverted::NAME, '2026-01-01T00:00:02+00:00', json_encode(['cartId' => $this->cartId], JSON_THROW_ON_ERROR)),
        ];
        $bus = self::getContainer()->get('event.bus');
        foreach ([2, 1, 0, 1, 2] as $index) {
            $bus->dispatch($events[$index]);
        }
        $this->request('GET', $path.'?limit=2');
        self::assertResponseIsSuccessful();
        $first = $this->body();
        self::assertIsArray($first['items']);
        self::assertSame([$events[0]->id, $events[1]->id], array_column($first['items'], 'eventId'));
        self::assertSame($events[1]->id, $first['nextCursor']);
        self::assertIsArray($first['items'][1]);
        self::assertSame(2, $first['items'][1]['quantity']);
        self::assertSame($this->productId, $first['items'][1]['productId']);

        $this->request('GET', $path.'?limit=2&after='.$events[1]->id);
        self::assertResponseIsSuccessful();
        $second = $this->body();
        self::assertIsArray($second['items']);
        self::assertSame([$events[2]->id], array_column($second['items'], 'eventId'));
        self::assertNull($second['nextCursor']);
        self::assertSame(3, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_activity WHERE cart_id = ?', [$this->cartId]));
    }

    #[Test]
    public function cartActivityRequiresAuthenticationAndOwnership(): void
    {
        $path = '/api/cart/'.$this->cartId.'/activity';
        $this->client->jsonRequest('GET', $path);
        self::assertResponseStatusCodeSame(401);
        $this->entityManager->getConnection()->update('cart', ['owner_id' => UserId::generate()->getId()], ['id' => $this->cartId]);
        $this->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->request('GET', '/api/cart/'.Uuid::v7()->toRfc4122().'/activity');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidActivityPages(): iterable
    {
        yield 'zero' => ['limit=0'];
        yield 'too large' => ['limit=101'];
        yield 'wrong type' => ['limit=abc'];
        yield 'invalid cursor' => ['after=invalid'];
    }

    #[Test]
    #[DataProvider('invalidActivityPages')]
    public function rejectsInvalidActivityPagination(string $query): void
    {
        $this->request('GET', '/api/cart/'.$this->cartId.'/activity?'.$query);

        self::assertResponseStatusCodeSame(422);
    }

    private function request(string $method, string $path): void
    {
        $this->client->jsonRequest($method, $path, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $body = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
