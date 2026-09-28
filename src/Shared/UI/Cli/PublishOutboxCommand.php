<?php

declare(strict_types=1);

namespace App\Shared\UI\Cli;

use App\Shared\Infrastructure\Outbox\OutboxPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:publish', description: 'Publish a batch of committed domain events to RabbitMQ')]
final class PublishOutboxCommand extends Command
{
    public function __construct(private readonly OutboxPublisher $publisher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of attempts (1-1000)', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if (false === $limit) {
            $io->error('Limit must be an integer between 1 and 1000.');

            return Command::INVALID;
        }

        $published = $failed = 0;
        for ($index = 0; $index < $limit; ++$index) {
            $result = $this->publisher->publishNext();
            if (null === $result) {
                break;
            }
            $result ? ++$published : ++$failed;
        }
        $io->writeln(sprintf('Published: %d; retained for retry: %d.', $published, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
