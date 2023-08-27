<?php

namespace SoureCode\Bundle\Daemon\Command;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

#[AsCommand(
    name: 'daemon:start',
    description: 'This command starts a daemon',
)]
final class DaemonStartCommand extends Command
{
    private DaemonManager $daemonManager;

    public function __construct(
        DaemonManager   $daemonManager,
    )
    {
        $this->daemonManager = $daemonManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'The command id')
            ->addArgument('process', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The process to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $command = $input->getArgument('process');

        if (is_array($command)) {
            $command = implode(" ", $command);
        }

        $this->daemonManager->start($id, $command);

        return Command::SUCCESS;
    }

}
