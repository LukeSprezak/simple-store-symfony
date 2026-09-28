<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Framework\Scheduler;

use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommand;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule(name: 'expired_cart')]
final readonly class RemoveExpiredCart implements ScheduleProviderInterface
{
    public function __construct(
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(frequency: '5 seconds', message: new RemoveExpiredCartCommand()))
            ->lock($this->lockFactory->createLock('schedule_expired_cart'))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
