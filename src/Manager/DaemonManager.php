<?php

namespace SoureCode\Bundle\Daemon\Manager;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SoureCode\Bundle\Daemon\Command\DaemonCommand;
use SoureCode\Bundle\Daemon\Pid\ManagedPid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

class DaemonManager
{
    private string $pidDirectory;
    private string $projectDirectory;
    private string $tmpDirectory;
    private Filesystem $filesystem;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        Filesystem      $filesystem,
        string          $projectDirectory,
        string          $pidDirectory,
        string          $tmpDirectory,
    )
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->projectDirectory = $projectDirectory;
        $this->pidDirectory = $pidDirectory;
        $this->tmpDirectory = $tmpDirectory;
    }

    public function pid(string $id, ?int $value = null): ManagedPid
    {
        return new ManagedPid($this->pidDirectory, $id, $value);
    }

    public function start(string $id, string $processCommand): void
    {
        $command = $this->buildCommand($id, $processCommand);

        $pid = $this->pid($id);

        if ($pid->exists()) {
            $this->logger->info('Daemon already running.', [
                ...$pid->toArray(),
                'command' => $processCommand,
            ]);

            return;
        }

        if (!$this->filesystem->exists($this->tmpDirectory)) {
            $this->filesystem->mkdir($this->tmpDirectory);
        }

        $tempLogFile = $this->filesystem->tempnam($this->tmpDirectory, $pid->getHash(), '.log');

        try {
            $bashBinary = $this->findBinary('bash');

            $shellCommand = implode(" ", [
                $bashBinary,
                '-c',
                self::escape($command),
                '>',
                $tempLogFile,
                '2>&1',
                '&',
                'disown',
                '$!',
            ]);

            $this->logger->info('Starting daemon...', [
                ...$pid->toArray(),
                'command' => $processCommand,
            ]);

            shell_exec($shellCommand);

            // wait for possible error
            sleep(1);

            $contents = file_get_contents($tempLogFile);

            if ('' !== $contents) {
                $keywords = [
                    'ERROR',
                    'PHP Fatal error',
                    'Exception',
                ];

                foreach ($keywords as $keyword) {
                    if (str_contains($contents, $keyword)) {
                        $exception = new RuntimeException($contents);

                        throw new RuntimeException("Daemon exited with an error.", 0, $exception);
                    }
                }
            }

            $pid->reload();

            $this->logger->info('Daemon started.', [
                ...$pid->toArray(),
                'command' => $processCommand,
            ]);
        } finally {
            $this->filesystem->remove($tempLogFile);
        }
    }

    private function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
    }

    private function getPhpBinary(): ?array
    {
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        if (false === $php) {
            return null;
        }

        return array_merge([$php], $executableFinder->findArguments());
    }

    private function findBinary(string $binary): ?string
    {
        return (new ExecutableFinder())
            ->find($binary);
    }

    /**
     * @copyright symfony/process - https://github.com/symfony/process
     */
    public static function escape(string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }

        if (str_contains($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }

        if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }

        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
    }

    private function buildCommand(string $id, string $processCommand): string
    {
        $command = $this->getPhpBinary();
        $command[] = $this->getConsolePath();
        $command[] = DaemonCommand::getDefaultName();
        $command[] = '--id';
        $command[] = $id;
        $command[] = self::escape($processCommand);

        return implode(" ", $command);
    }

    public function stopAll(): void
    {
        $this->logger->info('Stopping all daemons...');

        $pidFiles = (new Finder())
            ->files()
            ->name('*.id') // by the id files NOT the pid files
            ->in($this->pidDirectory);

        foreach ($pidFiles as $pidFile) {
            $filePath = $pidFile->getRealPath();

            if ($this->filesystem->exists($filePath) === false) {
                continue;
            }

            $id = (int)file_get_contents($filePath);

            $this->stop($id);

            $this->filesystem->remove($pidFile->getRealPath());
        }

        $this->logger->info('All daemons stopped.');
    }

    public function stop(string $id): void
    {
        $pid = $this->pid($id);

        $this->logger->info('Stopping daemon...', [
            ...$pid->toArray(),
        ]);

        $unmanagedPid = $pid->getPid();

        if (null !== $unmanagedPid && $unmanagedPid->isRunning()) {
            $unmanagedPid->gracefullyStop();

            $this->logger->info('Daemon stopped.', [
                ...$pid->toArray(),
            ]);
        } else {
            $this->logger->warning('Daemon not running.', [
                ...$pid->toArray(),
            ]);
        }

    }

    public function isRunning(string $id): bool
    {
        return $this->pid($id)->isRunning();
    }
}