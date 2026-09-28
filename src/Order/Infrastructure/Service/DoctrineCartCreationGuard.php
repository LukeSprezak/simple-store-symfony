<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Service;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\ActiveCartLimitExceededException;
use App\Order\Domain\Exception\CartOwnerNotFoundException;
use App\Order\Domain\Policy\CartLimits;
use App\Order\Domain\Service\CartCreationGuard;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrineCartCreationGuard implements CartCreationGuard
{
    public function __construct(private Connection $connection)
    {
    }

    public function assertCanCreate(UserId $ownerId): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('The cart creation limit must be checked inside a transaction.');
        }

        // An existing owner row also serializes requests when the user has no carts yet.
        $owner = $this->connection->fetchOne('SELECT id FROM `user` WHERE id = :owner FOR UPDATE', ['owner' => $ownerId->getId()]);
        if (false === $owner) {
            throw new CartOwnerNotFoundException();
        }

        // A locking read sees committed creations even with an older REPEATABLE READ snapshot.
        // Expired timestamps still count until the scheduler actually releases the reservation.
        $cartIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM cart WHERE owner_id = :owner AND status IN (:active, :abandoned) LIMIT :limit FOR UPDATE',
            [
                'owner' => $ownerId->getId(),
                'active' => StatusCart::ACTIVE->value,
                'abandoned' => StatusCart::ABANDONED->value,
                'limit' => CartLimits::MAX_ACTIVE_CARTS_PER_OWNER,
            ],
            ['limit' => ParameterType::INTEGER],
        );

        if (count($cartIds) >= CartLimits::MAX_ACTIVE_CARTS_PER_OWNER) {
            throw new ActiveCartLimitExceededException();
        }
    }
}
