<?php

namespace SoureCode\Bundle\Daemon\Command;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

#[AsCommand(
    name: 'daemon:start',
    description: 'This command starts a daemon',
)]
final class DaemonStartCommand extends Command
{
    private Filesystem $filesystem;

    private LoggerInterface $logger;
    private string $tmpDirectory;
    private string $projectDirectory;

    public function __construct(
        LoggerInterface $logger,
        Filesystem      $filesystem,
        string          $tmpDirectory,
        string          $projectDirectory
    )
    {
        $this->filesystem = $filesystem;
        $this->tmpDirectory = $tmpDirectory;
        $this->projectDirectory = $projectDirectory;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'The command id')
            ->addArgument('process', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The process to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $command = $input->getArgument('process');

        if (is_array($command)) {
            $command = implode(" ", $command);
        }

        $phpCommand = $this->phpCommand();
        $phpCommand[] = $this->getConsolePath();
        $phpCommand[] = DaemonCommand::getDefaultName();
        $phpCommand[] = '--id';
        $phpCommand[] = $id;
        $phpCommand[] = $this->escape($command);

        $pidFileName = DaemonCommand::getPidFileName($id);

        if (!$this->filesystem->exists($this->tmpDirectory)) {
            $this->filesystem->mkdir($this->tmpDirectory);
        }

        $tempLogFile = $this->filesystem->tempnam($this->tmpDirectory, $pidFileName, '.log');

        try {
            $phpCommand = implode(" ", $phpCommand);
            $bash = $this->findBinary('bash');

            $shellCommand = implode(" ", [
                $bash,
                '-c',
                $this->escape($phpCommand),
                '>',
                $tempLogFile,
                '2>&1',
                '&',
                'disown',
                '$!',
            ]);

            $this->logger->info('Starting daemon...', [
                'id' => $id,
                'command' => $command,
            ]);

            shell_exec($shellCommand);

            // wait for possible error
            sleep(2);

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

            $this->logger->info('Daemon started.', [
                'id' => $id,
                'command' => $command,
            ]);
        } finally {
            $this->filesystem->remove($tempLogFile);
        }

        return Command::SUCCESS;
    }

    private function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
    }

    private function phpCommand(): ?array
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
     * @copyright From symfony/process component.
     */
    private function escape(string $argument): string
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
}
