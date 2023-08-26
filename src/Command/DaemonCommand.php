<?php

namespace SoureCode\Bundle\Daemon\Command;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'daemon',
    description: 'This command is the daemon supervisor',
)]
final class DaemonCommand extends Command implements SignalableCommandInterface
{
    private LoggerInterface $logger;
    private ClockInterface $clock;
    private Filesystem $filesystem;
    private string $pidDirectory;
    private string $projectDirectory;
    private ?Process $process = null;
    private bool $shouldExit = false;
    private int $exitCode = Command::SUCCESS;
    private ?string $id = null;
    private ?string $command = null;
    private ?int $pid = null;
    private bool $autoRestart = true;

    public function __construct(
        LoggerInterface $logger,
        ClockInterface  $clock,
        Filesystem      $filesystem,
        string          $projectDirectory,
        string          $pidDirectory,
    )
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
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
        ];
    }

    public function handleSignal(int $signal /*, int|false $previousExitCode*/): int
    {
        $this->shouldExit = true;

        if (null !== $this->process && $this->process->isRunning()) {
            $this->logger->info('Stopping daemon...', $this->getContext());
            $pid = $this->process->getPid();

            posix_kill($pid, $signal);

            for ($i = 0; $i < 5; $i++) {
                if ($this->process->isRunning() === false) {
                    break;
                }

                $this->logger->info('Daemon still running...', $this->getContext());

                sleep(1);
            }

            if ($this->process->isRunning()) {
                $this->logger->warning('Daemon did not stop.', $this->getContext());

                if ($signal === 15) {
                    $this->logger->info('Killing daemon...', $this->getContext());
                    posix_kill($pid, 9);
                    $this->process->stop(10, 9);
                    $this->logger->info('Daemon killed.', $this->getContext());
                } else {
                    // continue daemon, as it did not stop
                    return false;
                }
            } else {
                $this->logger->info('Daemon stopped.', $this->getContext());
            }
        }

        $this->removePidFile();

        return $this->exitCode;
    }

    private function isRunning(): bool
    {
        return $this->sendSignal(0);
    }

    public function sendSignal(int $signal): bool
    {
        $filePath = $this->getPidFilePath();

        if ($this->filesystem->exists($filePath) === false) {
            return false;
        }

        if ($this->pid === null) {
            $this->pid = (int)file_get_contents($filePath);
        }

        return posix_kill($this->pid, $signal);
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
        $this->id = $input->getOption('id');
        $this->autoRestart = $input->getOption('auto-restart');
        $command = $input->getArgument('process');

        if (is_array($command)) {
            $this->command = implode(" ", $command);
        } else {
            $this->command = $command;
        }

        if ($this->isRunning()) {
            $this->logger->warning('Daemon already running.', $this->getContext());

            return Command::FAILURE;
        }

        $this->logger->info('Starting Daemon...', $this->getContext());

        while ($this->shouldExit === false) {
            $before = $this->clock->now();
            $this->exitCode = $this->runProcess($output);
            $this->process = null;

            $after = $this->clock->now();
            $diff = $after->getTimestamp() - $before->getTimestamp();

            if ($diff < 1 || $this->exitCode !== Command::SUCCESS) {
                $this->shouldExit = true;

                $this->logger->error('Daemon exited to fast or with an error.', [
                    'exitCode' => $this->exitCode,
                    ...$this->getContext(),
                ]);
            } else {
                $this->logger->info('Daemon exited.', [
                    'exitCode' => $this->exitCode,
                    ...$this->getContext(),
                ]);

                if (!$this->autoRestart) {
                    $this->shouldExit = true;
                    continue;
                }

                $this->logger->info('Restarting daemon...', $this->getContext());
            }
        }

        $this->logger->info('Daemon stopped.', $this->getContext());

        $this->removePidFile();

        return $this->exitCode;
    }

    private function runProcess(OutputInterface $output): int
    {
        $this->process = Process::fromShellCommandline(
            $this->command,
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

        $pid = getmypid();
        $this->dumpPidFile($pid);

        $this->logger->info('Daemon started.', $this->getContext());

        return $this->process->wait();
    }

    private function dumpPidFile(int $pid): void
    {
        $this->pid = $pid;

        $this->filesystem->dumpFile($this->getPidFilePath(), $this->pid);
    }

    private function removePidFile(): void
    {
        $filePath = $this->getPidFilePath();

        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }
    }

    public function getPidFilePath(): string
    {
        $fileName = self::getPidFileName($this->id);

        if (!$this->filesystem->exists($this->pidDirectory)) {
            $this->filesystem->mkdir($this->pidDirectory);
        }

        return Path::join($this->pidDirectory, $fileName);
    }

    public static function getPidFileName(string $id): string
    {
        $hash = hash('sha256', $id);

        return sprintf("%s.pid", $hash);
    }

    private function getContext(): array
    {
        return [
            'id' => $this->id,
            'command' => $this->command,
            'pid' => $this->pid,
        ];
    }
}
