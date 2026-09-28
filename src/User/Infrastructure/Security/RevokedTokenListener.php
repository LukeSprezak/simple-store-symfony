<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

// Rejects JWTs issued before the user's last password change.
#[AsEventListener(dispatcher: 'security.event_dispatcher.api')]
final readonly class RevokedTokenListener
{
    public function __invoke(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        /** @var array{iat: int} $payload */
        $payload = $passport->getAttribute('payload');
        $user = $passport->getUser();

        if ($user instanceof User && null !== $user->getLastPasswordChange() && $payload['iat'] < $user->getLastPasswordChange()->getTimestamp()) {
            throw new CustomUserMessageAuthenticationException('Token has been revoked.');
        }
    }
}
