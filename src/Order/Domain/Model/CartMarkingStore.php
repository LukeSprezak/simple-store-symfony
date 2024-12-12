<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Enum\StatusCart;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

class CartMarkingStore implements MarkingStoreInterface
{
    public function getMarking(object $subject): Marking
    {
        if (!$subject instanceof Cart) {
            throw new \InvalidArgumentException('Subject must be an instance of Cart.');
        }

        return new Marking([$subject->getStatus()->value => 1]);
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        if (!$subject instanceof Cart) {
            throw new \InvalidArgumentException('Subject must be an instance of Cart.');
        }

        $places = array_keys($marking->getPlaces());

        if (1 !== count($places)) {
            throw new \InvalidArgumentException('Marking must contain exactly one place.');
        }

        $newStatusValue = $places[0];
        $newStatus = StatusCart::tryFrom($newStatusValue);

        if (null === $newStatus) {
            throw new \InvalidArgumentException(sprintf("Invalid status value '%s'.", $newStatusValue));

        }

        $subject->setStatus($newStatus);
    }
}
