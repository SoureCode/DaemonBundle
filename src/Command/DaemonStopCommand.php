<?php

namespace SoureCode\Bundle\Daemon\Command;

use RuntimeException;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Stop all daemons')
            ->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'The daemon id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $all = $input->getOption('all');
        $id = $input->getOption('id');

        if ($all && $id) {
            throw new RuntimeException('You cannot use --all and --id at the same time.');
        }

        if (!$all && !$id) {
            throw new RuntimeException('You must use --all or --id.');
        }

        if ($all) {
            $this->daemonManager->stopAll();
        } else {
            $this->daemonManager->stop($id);
        }

        return Command::SUCCESS;
    }
}
