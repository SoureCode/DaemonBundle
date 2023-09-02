<?php

namespace SoureCode\Bundle\Daemon\Command;

use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[AsCommand(
    name: 'daemon:start',
    description: 'This command starts a daemon',
)]
#[Autoconfigure(tags: ['monolog.logger' => 'daemon'])]
final class DaemonStartCommand extends Command
{
    private DaemonManager $daemonManager;

    public function __construct(
        DaemonManager $daemonManager,
    )
    {
        $this->daemonManager = $daemonManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'The command id')
            ->addArgument('process', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The process to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $command = $input->getArgument('process');

        if (is_array($command)) {
            $command = implode(" ", $command);
        }

        $started = $this->daemonManager->start($id, $command);

        if ($started) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

}
