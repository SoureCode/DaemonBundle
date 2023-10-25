<?php

namespace SoureCode\Bundle\Daemon\Command;

use RuntimeException;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[AsCommand(
    name: 'daemon:stop',
    description: 'This command stop daemons',
)]
final class DaemonStopCommand extends Command
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
            ->addOption('all', 'a', InputOption::VALUE_OPTIONAL, 'Stop all daemons, optionally filtered by pattern')
            ->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The name of the daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $all = $input->getOption('all');
        $name = $input->getArgument('name');

        if (null !== $all) {
            if (is_string($all)) {
                $this->daemonManager->stopAll($all);
            } else {
                $this->daemonManager->stopAll();
            }
        }

        if (null === $name) {
            throw new RuntimeException('You need to specify a daemon name or use the --all option.');
        }

        if (is_array($name)) {
            foreach ($name as $daemonName) {
                $this->daemonManager->stop($daemonName);
            }
        } else {
            $this->daemonManager->stop($name);
        }

        return Command::SUCCESS;
    }
}
