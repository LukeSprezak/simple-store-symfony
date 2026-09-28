<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Framework\Event\Subscriber;

use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class RateLimiterSubscriberTest extends WebTestCase
{
    private const string LOGIN_CHECK_URI = '/api/login_check';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if ($existingUser) {
            $entityManager->remove($existingUser);
            $entityManager->flush();
        }

        $user = new User();
        $hashedPassword = $passwordHasher->hashPassword($user, 'testpassword');
        $user
            ->setId(Uuid::v7()->toRfc4122())
            ->setUsername('test')
            ->setEmail('test@example.com')
            ->setRoles(['ROLE_USER'])
            ->setPassword($hashedPassword)
            ->setEnabled(true);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    private function getJwtToken(): string
    {
        $this->client->request('POST', self::LOGIN_CHECK_URI, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ], JSON_THROW_ON_ERROR));

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $this->assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);

        return $data['token'];
    }

    #[Test]
    public function rateLimiterBlocksExcessiveRequests(): void
    {
        static::getContainer()->get('limiter.anonymous_api')->create('127.0.0.1')->reset();
        $token = $this->getJwtToken();
        static::getContainer()->get('limiter.anonymous_api')->create('127.0.0.1')->reset();

        $limit = 5;
        $route = '/api';

        for ($index = 1; $index <= $limit; ++$index) {
            $this->client->request('GET', $route, [], [], [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_Authorization' => 'Bearer '.$token,
            ]);

            $response = $this->client->getResponse();
            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        }

        $this->client->request('GET', $route, [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_Authorization' => 'Bearer '.$token,
        ]);
        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertIsNumeric($retryAfter);

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $content = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(['error' => 'Too many requests. Try again in a few moments.'], $content);
    }
}
