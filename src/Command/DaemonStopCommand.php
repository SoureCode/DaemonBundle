<?php

namespace SoureCode\Bundle\Daemon\Command;

use RuntimeException;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Daemon\Pid\UnmanagedPid;
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
            ->addOption('pattern', 'p', InputOption::VALUE_OPTIONAL, 'The pattern to match daemons')
            ->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'The daemon id')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'The timeout before sending next signal')
            ->addOption('signal', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The signal to send');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $all = $input->getOption('all');
        $pattern = $input->getOption('pattern') ?? null;
        $id = $input->getOption('id');
        $timeout = $input->getOption('timeout') ?? 10;
        $signals = $input->getOption('signal') ?? null;

        if (!is_numeric($timeout)) {
            throw new RuntimeException('Timeout must be numeric.');
        }

        $timeout = (int)$timeout;

        UnmanagedPid::validateSignals($signals);

        if ($all && $id) {
            throw new RuntimeException('You cannot use --all and --id at the same time.');
        }

        if (!$all && !$id) {
            throw new RuntimeException('You must use --all or --id.');
        }

        if ($id && $pattern) {
            throw new RuntimeException('You cannot use --id and --pattern at the same time.');
        }

        if($pattern) {
            $pattern = sprintf("/%s/i", preg_quote($pattern, '/'));
        }

        if ($all) {
            $this->daemonManager->stopAll($pattern, $timeout, $signals);
        } else {
            $this->daemonManager->stop($id, $timeout, $signals);
        }

        return Command::SUCCESS;
    }
}
