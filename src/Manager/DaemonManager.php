<?php

namespace SoureCode\Bundle\Daemon\Manager;

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Command\DaemonCommand;
use SoureCode\Bundle\Daemon\Pid\ManagedPid;
use SoureCode\Bundle\Daemon\Time;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use const DIRECTORY_SEPARATOR;

class DaemonManager
{
    private static array $errorKeywords = [
        // Error from logger
        "[ERROR]",
        // PHP Fatal error
        "PHP Fatal error",
        // Any Exception
        "Exception",
    ];

    private string $pidDirectory;
    private string $projectDirectory;
    private string $tmpDirectory;
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    /**
     * @var int Delay in microseconds between checks.
     */
    private int $checkDelay;
    private int $checkTimeout;

    public function __construct(
        LoggerInterface $logger,
        Filesystem      $filesystem,
        string          $projectDirectory,
        string          $pidDirectory,
        string          $tmpDirectory,
        int             $checkDelay,
        int             $checkTimeout,
    )
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->projectDirectory = $projectDirectory;
        $this->pidDirectory = $pidDirectory;
        $this->tmpDirectory = $tmpDirectory;
        $this->checkDelay = $checkDelay;
        $this->checkTimeout = $checkTimeout;
    }

    public function start(string $id, string $processCommand): bool
    {
        $command = $this->buildCommand($id, $processCommand);

        $pid = $this->pid($id);

        if ($pid->exists()) {
            $this->logger->info('Daemon already running.', [
                'pid' => (string)$pid,
                'command' => $processCommand,
            ]);

            return false;
        }

        if (!$this->filesystem->exists($this->tmpDirectory)) {
            $this->filesystem->mkdir($this->tmpDirectory);
        }

        $commandLogFile = $this->filesystem->tempnam($this->tmpDirectory, $pid->getHash(), '_bash.log');
        $bashLogFile = $this->filesystem->tempnam($this->tmpDirectory, $pid->getHash(), '_command.log');

        try {
            $bashBinary = $this->findBinary('bash');

            $bashCommand = [
                $bashBinary,
                '-c',
                self::escape($command),
                ">",
                $commandLogFile,
                "2>&1",
                "&",
                "disown",
                '$!',
            ];

            $bashCommand = implode(" ", $bashCommand);

            $shellCommand = implode(" ", [
                $bashBinary,
                '-c',
                self::escape($bashCommand),
                ">",
                $bashLogFile,
                "2>&1",
            ]);

            $this->logger->info('Starting daemon...', [
                'pid' => (string)$pid,
                'command' => $processCommand,
            ]);

            shell_exec($shellCommand);

            $this->doCheck(function () use ($pid) {
                return $pid->isRunning();
            });

            $bashLog = trim(file_get_contents($bashLogFile));
            $commandLog = trim(file_get_contents($commandLogFile));

            if ($bashLog !== '') {
                $this->logger->error('Daemon bash contains output.', [
                    'pid' => (string)$pid,
                    'command' => $processCommand,
                    'bash_log' => $bashLog,
                ]);

                return false;
            }

            if ('' !== $commandLog) {
                if ($this->containsKeyword($commandLog)) {
                    $this->logger->error('Daemon command output contains error keyword.', [
                        'pid' => (string)$pid,
                        'command' => $processCommand,
                        'command_log' => $commandLog,
                    ]);

                    return false;
                }
            }

            if (!$pid->isRunning()) {
                $this->logger->error('Daemon crashed after start.', [
                    'pid' => (string)$pid,
                    'command' => $processCommand,
                    'bash_log' => $bashLog,
                    'command_log' => $commandLog,
                ]);

                return false;
            }

            $this->logger->info('Daemon started.', [
                'pid' => (string)$pid,
                'command' => $processCommand,
            ]);

            return true;
        } finally {
            $this->filesystem->remove($commandLogFile);
            $this->filesystem->remove($bashLogFile);
        }
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

    private function getPhpBinary(): ?array
    {
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        if (false === $php) {
            return null;
        }

        return array_merge([$php], $executableFinder->findArguments());
    }

    private function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
    }

    /**
     * @copyright symfony/process - https://github.com/symfony/process
     */
    public static function escape(string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }

        if ('\\' !== DIRECTORY_SEPARATOR) {
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

    public function pid(string $id, ?int $value = null): ManagedPid
    {
        return new ManagedPid($this->pidDirectory, $id, $value);
    }

    private function findBinary(string $binary): ?string
    {
        return (new ExecutableFinder())
            ->find($binary);
    }

    private function containsKeyword(string $log): bool
    {
        foreach (self::$errorKeywords as $keyword) {
            if (str_contains($log, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function isRunning(string $id): bool
    {
        return $this->pid($id)->isRunning();
    }

    /**
     * @param string|null $idPattern Pattern to match the daemon id.
     * @param int $timeout Timeout in seconds before sending the next signal.
     * @param array|null $signals Ordered list of signals to send.
     * @return bool true if all daemons were stopped successfully
     */
    public function stopAll(?string $idPattern = null, int $timeout = 10, ?array $signals = null): bool
    {
        $this->logger->info('Stopping all daemons...', [
            'id_pattern' => $idPattern,
        ]);

        $pidFiles = (new Finder())
            ->files()
            ->name('*.id') // by the id files NOT the pid files
            ->in($this->pidDirectory);

        $stopResults = [];

        foreach ($pidFiles as $pidFile) {
            $filePath = $pidFile->getRealPath();

            if ($this->filesystem->exists($filePath) === false) {
                continue;
            }

            $id = file_get_contents($filePath);

            if (null !== $idPattern && !preg_match($idPattern, $id)) {
                $this->logger->info('Skipping stop daemon...', [
                    'id' => $id,
                ]);
                continue;
            }

            $pid = $this->pid($id);

            $stopped = $this->stop($pid, $timeout, $signals);

            if ($stopped) {
                $pid->remove();
            }

            $stopResults[(string)$pid] = $stopped;
        }

        $allStopped = !in_array(false, $stopResults, true);

        if ($allStopped) {
            $this->logger->info('All daemons stopped.', [
                'stop_results' => $stopResults,
            ]);
        } else {
            $this->logger->error('Not all daemons stopped.', [
                'stop_results' => $stopResults,
            ]);
        }

        return $allStopped;
    }

    /**
     * @param string $id Daemon id.
     * @param int $timeout Timeout in seconds before sending the next signal.
     * @param array|null $signals Ordered list of signals to send.
     * @return bool true if the process is stopped, false otherwise.
     */
    public function stop(string|ManagedPid $pid, int $timeout = 10, ?array $signals = null): bool
    {
        if (is_string($pid)) {
            $pid = $this->pid($pid);
        }

        $this->logger->info('Stopping daemon...', [
            'pid' => (string)$pid,
        ]);

        if ($pid->isRunning()) {
            $stopped = $pid->stop($timeout, $signals);

            if (!$stopped) {
                $this->logger->error('Daemon could not be stopped.', [
                    'pid' => (string)$pid,
                ]);

                return false;
            }

            $this->logger->info('Daemon stopped.', [
                'pid' => (string)$pid,
            ]);

            $pid->remove();

            return true;
        }

        $this->logger->warning('Daemon not running.', [
            'pid' => (string)$pid,
        ]);

        return false;

    }

    private function doCheck(\Closure $param): void
    {
        $timeoutInMicroseconds = $this->checkTimeout * 1000 * 1000;
        $iterations = (int)($timeoutInMicroseconds / $this->checkDelay);

        for ($i = 0; $i < $iterations; $i++) {
            if ($param()) {
                return;
            }

            usleep($this->checkDelay);
        }
    }
}