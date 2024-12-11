<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Framework\Scheduler;

use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule(name: 'expired_cart')]
final readonly class RemoveExpiredCart implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every(frequency: '5 seconds', message: new RemoveExpiredCartCommand()),
        );
    }
}
