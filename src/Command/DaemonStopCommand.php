<?php

namespace SoureCode\Bundle\Daemon\Command;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'daemon:stop',
    description: 'This command stop daemons',
)]
final class DaemonStopCommand extends Command
{
    private LoggerInterface $logger;
    private Filesystem $filesystem;
    private string $pidDirectory;
    private ?string $id = null;

    public function __construct(
        LoggerInterface $logger,
        Filesystem      $filesystem,
        string          $pidDirectory,
    )
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->pidDirectory = $pidDirectory;

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
            $this->logger->info('Stopping all daemons...', $this->getContext());

            $pidFiles = (new Finder())
                ->files()
                ->name('*.pid')
                ->in($this->pidDirectory);

            foreach ($pidFiles as $pidFile) {
                $filePath = $pidFile->getRealPath();

                if ($this->filesystem->exists($filePath) === false) {
                    continue;
                }

                $pid = (int)file_get_contents($filePath);

                $this->stop($pid);

                $this->filesystem->remove($pidFile->getRealPath());
            }

            $this->logger->info('All daemons stopped.', $this->getContext());
        } else {
            $pid = $this->getPid($id);

            $this->stop($pid);
        }

        return Command::SUCCESS;
    }

    public function getPidFilePath(string $id): string
    {
        $fileName = DaemonCommand::getPidFileName($id);

        if (!$this->filesystem->exists($this->pidDirectory)) {
            $this->filesystem->mkdir($this->pidDirectory);
        }

        return Path::join($this->pidDirectory, $fileName);
    }

    public function sendSignal(?int $pid, int $signal): bool
    {
        if ($pid === null) {
            return false;
        }

        return posix_kill($pid, $signal);
    }

    private function isRunning(?int $pid): bool
    {
        return $this->sendSignal($pid, 0);
    }

    private function stop(?int $pid = null): void
    {
        if (!$this->isRunning($pid)) {
            $this->logger->warning('Daemon is not running.', $this->getContext($pid));

            return;
        }

        $this->logger->info('Stopping daemon with SIGINT...', [
            ...$this->getContext($pid),
            'signal' => 2,
        ]);

        $this->sendSignal($pid, 2); // SIGINT

        $stopped = $this->wait($pid, 2);

        if ($stopped) {
            return;
        }

        $this->logger->info('Stopping daemon with SIGTERM.', [
            ...$this->getContext($pid),
            'signal' => 15,
        ]);

        $this->sendSignal($pid, 15); // SIGTERM

        $stopped = $this->wait($pid, 15);

        if ($stopped) {
            return;
        }

        if ($this->isRunning($pid)) {
            $this->logger->warning('Killing daemon...', $this->getContext($pid));

            $this->sendSignal($pid, 9); // SIGKILL

            $this->logger->warning('Daemon killed.', $this->getContext($pid));
        }
    }

    private function getContext(?int $pid = null): array
    {
        $context = [];

        if (null !== $this->id) {
            $context['id'] = $this->id;
        }

        if (null !== $pid) {
            $context['pid'] = $pid;
        }

        return $context;
    }

    private function getPid(string $id): ?int
    {
        $pidFilePath = $this->getPidFilePath($id);

        if ($this->filesystem->exists($pidFilePath) === false) {
            return null;
        }

        return (int)file_get_contents($pidFilePath);
    }

    private function wait(?int $pid, int $signal): bool
    {
        for ($i = 0; $i < 10; $i++) {
            $this->logger->info('Waiting for daemon to stop...', [
                'retry' => $i + 1,
                'signal' => $signal,
                ...$this->getContext($pid),
            ]);

            if (!$this->isRunning($pid)) {
                $this->logger->info('Daemon stopped.', [
                    'retries' => $i + 1,
                    'signal' => $signal,
                    ...$this->getContext($pid),
                ]);

                return true;
            }

            sleep(1);
        }

        return false;
    }
}
