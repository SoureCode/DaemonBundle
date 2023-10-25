<?php

namespace SoureCode\Bundle\Daemon\Command;

use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

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
            ->addArgument('name', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The name of the daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (is_array($name)) {
            foreach ($name as $daemonName) {
                $this->daemonManager->start($daemonName);
            }
        } else {
            $this->daemonManager->start($name);
        }

        return Command::SUCCESS;
    }
}
