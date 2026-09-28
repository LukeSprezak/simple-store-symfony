<?php

declare(strict_types=1);

namespace App\Shared\UI\Cli;

use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:prune', description: 'Delete published domain events older than the given number of days')]
final class PruneOutboxCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention period in days (at least 1)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $days) {
            $io->error('Days must be an integer of at least 1.');

            return Command::INVALID;
        }

        $cutoff = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $deleted = 0;
        // Small batches keep row locks short while publishers run concurrently.
        do {
            $affected = (int) $this->connection->executeStatement(
                'DELETE FROM domain_event_outbox WHERE published_at IS NOT NULL AND published_at < :cutoff ORDER BY id LIMIT 1000',
                ['cutoff' => $cutoff]
            );
            $deleted += $affected;
        } while ($affected > 0);
        $io->writeln(sprintf('Deleted published events: %d.', $deleted));

        return Command::SUCCESS;
    }
}
