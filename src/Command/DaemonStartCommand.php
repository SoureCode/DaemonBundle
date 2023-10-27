<?php

namespace SoureCode\Bundle\Daemon\Command;

use RuntimeException;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'daemon:start',
    description: 'This command starts a daemon',
)]
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
            ->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The name of the daemon')
            ->addOption('all', InputArgument::OPTIONAL, 'Start all daemons, optionally filtered by pattern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $all = $input->getOption('all');

        if (null === $name && null === $all) {
            throw new RuntimeException('You need to specify a daemon name or use the --all option.');
        }

        if (null !== $name && null !== $all) {
            throw new RuntimeException('You can not specify a daemon name and use the --all option at the same time.');
        }

        if (null !== $all) {
            $started = is_string($all) ? $this->daemonManager->startAll($all) : $this->daemonManager->startAll();

            return $started ? Command::SUCCESS : Command::FAILURE;
        }

        if (is_array($name)) {
            $started = [];

            foreach ($name as $daemonName) {
                $started[] = $this->daemonManager->start($daemonName);
            }

            return in_array(false, $started, true) ? Command::FAILURE : Command::SUCCESS;
        }

        return $this->daemonManager->start($name) ? Command::SUCCESS : Command::FAILURE;
    }
}
