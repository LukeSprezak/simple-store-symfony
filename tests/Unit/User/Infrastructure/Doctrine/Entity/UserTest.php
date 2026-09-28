<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Doctrine\Entity;

use App\User\Infrastructure\Doctrine\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function emptyTokenSlotsCannotAuthenticate(): void
    {
        $user = new User();

        self::assertNull($user->getTokenHash());
        self::assertNull($user->getResetPasswordTokenHash());
        self::assertFalse($user->matchesToken(''));
        self::assertFalse($user->matchesToken('unknown'));
        self::assertFalse($user->matchesResetPasswordToken(''));
        self::assertFalse($user->matchesResetPasswordToken('unknown'));
    }

    #[Test]
    public function hashesTokensAndKeepsTheirPurposesSeparate(): void
    {
        $user = new User();
        $user->setToken('activation-token');
        $user->setResetPasswordToken('reset-token');

        self::assertSame(hash('sha256', 'activation-token'), $user->getTokenHash());
        self::assertSame(hash('sha256', 'reset-token'), $user->getResetPasswordTokenHash());
        self::assertTrue($user->matchesToken('activation-token'));
        self::assertTrue($user->matchesResetPasswordToken('reset-token'));
        self::assertFalse($user->matchesToken('reset-token'));
        self::assertFalse($user->matchesResetPasswordToken('activation-token'));
        self::assertFalse($user->matchesToken(''));
        self::assertFalse($user->matchesResetPasswordToken(''));
        self::assertFalse($user->matchesToken(hash('sha256', 'activation-token')));
        self::assertFalse($user->matchesResetPasswordToken(hash('sha256', 'reset-token')));
    }

    #[Test]
    public function replacingTokensInvalidatesPreviousValues(): void
    {
        $user = new User();
        $user->setToken('old-activation');
        $user->setResetPasswordToken('old-reset');
        $user->setToken('new-activation');
        $user->setResetPasswordToken('new-reset');

        self::assertFalse($user->matchesToken('old-activation'));
        self::assertFalse($user->matchesResetPasswordToken('old-reset'));
        self::assertTrue($user->matchesToken('new-activation'));
        self::assertTrue($user->matchesResetPasswordToken('new-reset'));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function clearedTokens(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[Test]
    #[DataProvider('clearedTokens')]
    public function clearingTokensInvalidatesThem(?string $value): void
    {
        $user = new User();
        $user->setToken('activation-token');
        $user->setResetPasswordToken('reset-token');
        $user->setToken($value);
        $user->setResetPasswordToken($value);

        self::assertNull($user->getTokenHash());
        self::assertNull($user->getResetPasswordTokenHash());
        self::assertFalse($user->matchesToken('activation-token'));
        self::assertFalse($user->matchesResetPasswordToken('reset-token'));
        self::assertFalse($user->matchesToken(''));
        self::assertFalse($user->matchesResetPasswordToken(''));
    }

    #[Test]
    public function erasingTransientCredentialsPreservesThePasswordHash(): void
    {
        $user = (new User())->setPassword('password-hash')->setPlainPassword('plaintext-password');
        self::assertSame('plaintext-password', $user->getPlainPassword());

        $user->eraseCredentials();

        self::assertNull($user->getPlainPassword());
        self::assertSame('password-hash', $user->getPassword());
    }
}
