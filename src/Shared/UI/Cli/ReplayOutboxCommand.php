<?php

declare(strict_types=1);

namespace App\Shared\UI\Cli;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:replay', description: 'Queue retained published domain events for republication')]
final class ReplayOutboxCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Event name to replay, e.g. order.status_changed (repeatable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $events */
        $events = $input->getOption('event');
        if ([] === $events) {
            $io->error('At least one --event is required.');

            return Command::INVALID;
        }

        // Events keep their UUIDs, so projections skip rows they already hold; truncate a projection to rebuild it.
        $queued = (int) $this->connection->executeStatement(
            'UPDATE domain_event_outbox SET published_at = NULL, available_at = :now, attempts = 0, last_error = NULL
             WHERE published_at IS NOT NULL AND event_name IN (:events)',
            ['now' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'events' => $events],
            ['events' => ArrayParameterType::STRING]
        );
        $io->writeln(sprintf('Queued for republication: %d. Run app:outbox:publish to send them.', $queued));

        return Command::SUCCESS;
    }
}
