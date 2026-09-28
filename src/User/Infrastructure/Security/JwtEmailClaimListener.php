<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Infrastructure\Doctrine\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::JWT_CREATED)]
final readonly class JwtEmailClaimListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $event->setData([...$event->getData(), 'email' => $user->getEmail()]);
    }
}
