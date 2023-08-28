<?php

namespace SoureCode\Bundle\Daemon\Command;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SoureCode\Bundle\Daemon\Pid\ManagedPid;
use SoureCode\Bundle\Daemon\Pid\UnmanagedPid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'daemon',
    description: 'This command is the daemon supervisor',
)]
final class DaemonCommand extends Command implements SignalableCommandInterface
{
    private LoggerInterface $logger;
    private ClockInterface $clock;
    private string $pidDirectory;
    private string $projectDirectory;
    private bool $shouldExit = false;
    private int $exitCode = Command::SUCCESS;
    private ?string $processCommand = null;
    private ?ManagedPid $pid = null;
    private ?Process $process = null;
    private bool $autoRestart = true;

    public function __construct(
        LoggerInterface $logger,
        ClockInterface  $clock,
        string          $projectDirectory,
        string          $pidDirectory,
    )
    {
        $this->logger = $logger;
        $this->projectDirectory = $projectDirectory;
        $this->pidDirectory = $pidDirectory;
        $this->clock = $clock;

        parent::__construct();
    }

    public function getSubscribedSignals(): array
    {
        return [
            15, // SIGTERM
            2, // SIGINT
            1, // SIGHUP
            3, // SIGQUIT
            6, // SIGABRT
        ];
    }

    public function handleSignal(int $signal /*, int|false $previousExitCode*/): int
    {
        $this->shouldExit = true;

        try {
            if (null !== $this->process && $this->process->isRunning()) {
                $ppid = $this->process->getPid();

                if ($ppid === null) {
                    return $this->process->getExitCode();
                }

                $pid = new UnmanagedPid($ppid);

                $this->logger->info('Stopping daemon process...', [
                    ...$this->getContext(),
                    'signal' => $signal,
                ]);

                $pid->gracefullyStop();

                if ($this->process->isRunning()) {
                    $this->logger->warning('Daemon process did not stop.', [
                        ...$this->getContext(),
                        'signal' => $signal,
                    ]);

                    if ($signal === 15) {
                        $this->logger->info('Killing daemon process...', [
                            ...$this->getContext(),
                            'signal' => $signal,
                        ]);
                        $pid->sendSignal(9); // SIGKILL
                        $this->process->stop(10, 9); // SIGKILL
                        $this->logger->info('Daemon process killed.', [
                            ...$this->getContext(),
                            'signal' => $signal,
                        ]);
                    } else {
                        if ($signal === 9) {
                            $this->pid->setPid(null);
                        }

                        // continue daemon, as it did not stop
                        return false;
                    }
                } else {
                    $this->logger->info('Daemon process stopped.', [
                        ...$this->getContext(),
                        'signal' => $signal,
                    ]);
                }
            }
        } finally {
            $this->pid->remove();
        }

        return $this->exitCode;
    }

    private function getContext(): array
    {
        return [
            'pid' => null !== $this->pid ? (string)$this->pid : null,
            'command' => $this->processCommand,
        ];
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'The daemon id')
            ->addOption('auto-restart', 'r', InputOption::VALUE_NEGATABLE, 'Auto restart daemon on exit', true)
            ->addArgument('process', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The process to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $this->autoRestart = $input->getOption('auto-restart');
        $processCommand = $input->getArgument('process');

        if (is_array($processCommand)) {
            $this->processCommand = implode(" ", $processCommand);
        } else {
            $this->processCommand = $processCommand;
        }

        $this->pid = new ManagedPid($this->pidDirectory, $id);

        if ($this->pid->isRunning()) {
            throw new RuntimeException('Daemon process already running.');
        }

        $this->pid->setPid(UnmanagedPid::fromGlobals());

        $this->logger->info('Starting daemon process...', $this->getContext());

        while ($this->shouldExit === false) {
            $before = $this->clock->now();
            $this->exitCode = $this->runProcess($output);
            $this->process = null;

            $after = $this->clock->now();
            $diff = $after->getTimestamp() - $before->getTimestamp();

            if ($diff < 1 || $this->exitCode !== Command::SUCCESS) {
                $this->shouldExit = true;

                $this->logger->error('Daemon process exited to fast or with an error.', [
                    'exitCode' => $this->exitCode,
                    ...$this->getContext(),
                ]);
            } else {
                $this->logger->info('Daemon process exited.', [
                    'exitCode' => $this->exitCode,
                    ...$this->getContext(),
                ]);

                if (!$this->autoRestart) {
                    $this->shouldExit = true;
                    continue;
                }

                if ($this->pid->willProcessExit()) {
                    $this->shouldExit = true;
                    continue;
                }

                $this->logger->info('Restarting daemon process...', $this->getContext());
            }
        }

        $this->logger->info('Daemon process stopped.', $this->getContext());

        $this->pid->remove();

        return $this->exitCode;
    }

    private function runProcess(OutputInterface $output): int
    {
        $this->process = Process::fromShellCommandline(
            $this->processCommand,
            $this->projectDirectory,
            null,
            null,
            0
        );

        $stdout = $output;
        $stderr = $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;

        $this->process->start(function ($type, $buffer) use ($stdout, $stderr) {
            if (Process::ERR === $type) {
                $stderr->write($buffer);
            } else {
                $stdout->write($buffer);
            }
        });

        $this->logger->info('Daemon process started.', $this->getContext());

        return $this->process->wait();
    }
}
