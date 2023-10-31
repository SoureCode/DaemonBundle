<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use Exception;
use RuntimeException;
use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SoureCode\Bundle\Daemon\Service\SystemdService;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class SystemdAdapter extends AbstractAdapter
{
    private ?string $userDirectory = null;
    private ?string $serviceDirectory = null;

    public function __construct(
        private readonly Filesystem $filesystem,
    )
    {
    }

    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface
    {
        $contents = file_get_contents($serviceFile->getPathname());
        $config = $this->parseFile($contents);

        return new SystemdService($name, $serviceFile, $config);
    }

    private function parseFile(string $contents): array
    {
        $lines = preg_split('/\r?\n/', $contents);
        $sections = [];
        $section = null;
        $ignore = false;

        foreach ($lines as $line) {
            if (empty($line) || $line[0] === '#' || $line[0] === ';') {
                if (str_ends_with($line, '\\')) {
                    $ignore = true;
                }

                continue;
            }

            if ($ignore) {
                if (str_ends_with($line, '\\')) {
                    $ignore = true;
                } else {
                    $ignore = false;
                }

                continue;
            }

            if (str_starts_with($line, '[') && str_ends_with($line, ']')) {
                $section = substr($line, 1, -1);
                $sections[$section] = [];
            } else {
                if (null === $section) {
                    throw new Exception("Invalid file format.");
                }

                $sections[$section][] = $line;
            }
        }

        return array_map($this->parseSection(...), $sections);
    }

    public function supports(SplFileInfo $serviceFile): bool
    {
        if (false === $serviceFile->isFile()) {
            return false;
        }

        if (false === $serviceFile->isReadable()) {
            return false;
        }

        if ('service' !== $serviceFile->getExtension()) {
            return false;
        }

        return true;
    }

    public function start(ServiceInterface $service): bool
    {
        if ($service instanceof SystemdService) {
            $this->load($service);
            $this->enable($service);

            if ($this->isRunning($service)) {
                return true;
            }

            $this->systemctl('--user', 'start', $service->getName());

            return $this->isRunning($service);
        }

        return false;
    }

    private function load(SystemdService $service): void
    {
        // Unload if loaded to ensure that the service is loaded with the latest configuration.
        if ($this->isLoaded($service)) {
            $this->unload($service);
        }

        $serviceFile = $this->getServiceFile($service);
        $this->filesystem->copy($service->getFilePath(), $serviceFile);

        $this->systemctl('--user', 'daemon-reload');
    }

    private function isLoaded(SystemdService $service): bool
    {
        $list = $this->getStatus($service);
        $columns = $this->findAndGetColumns($list, "Loaded: ");

        if (null !== $columns) {
            return $columns[1] === 'loaded';
        }

        return false;
    }

    public function getStatus(ServiceInterface $service): string
    {
        return $this->systemctl('--user', 'status', $service->getName());
    }

    /**
     * @param string ...$args
     * @return string
     */
    private function systemctl(...$args): string
    {
        $uid = posix_getuid();

        $process = new Process([
            'systemctl',
            ...$args,
        ], env: [
            'XDG_RUNTIME_DIR' => '/run/user/' . $uid,
            'DBUS_SESSION_BUS_ADDRESS' => 'unix:path=/run/user/' . $uid . '/bus',
        ]);

        $process->run();

        return $process->getOutput();
    }

    private function unload(SystemdService $service): void
    {
        if (!$this->isLoaded($service)) {
            return;
        }

        $serviceFile = $this->getServiceFile($service);
        $this->filesystem->remove($serviceFile);

        $this->systemctl('--user', 'daemon-reload');
        $this->systemctl('--user', 'reset-failed');
    }

    private function getServiceFile(SystemdService $service): string
    {
        return $this->getServiceDirectory() . '/' . $service->getName() . '.service';
    }

    private function getServiceDirectory(): string
    {
        if (null === $this->serviceDirectory) {
            $userDirectory = $this->getUserDirectory();
            $serviceDirectory = $userDirectory . '/.config/systemd/user';

            if (!$this->filesystem->exists($serviceDirectory)) {
                $this->filesystem->mkdir($serviceDirectory);
            }

            $this->serviceDirectory = $serviceDirectory;
        }

        return $this->serviceDirectory;
    }

    private function getUserDirectory(): string
    {
        if (null === $this->userDirectory) {
            if (isset($_SERVER['HOME'])) {
                $this->userDirectory = $_SERVER['HOME'];
            } else {
                $env = getenv('HOME');

                if (is_string($env)) {
                    $this->userDirectory = $env;
                } else {
                    throw new RuntimeException('Could not determine user directory.');
                }
            }
        }

        return $this->userDirectory;
    }

    private function enable(SystemdService $service): void
    {
        if (!$this->isEnabled($service)) {
            $this->systemctl('--user', 'enable', $service->getName());
        }
    }

    private function isEnabled(SystemdService $service): bool
    {
        $list = $this->systemctl('--user', 'list-unit-files', '--type=service', '--all');
        $columns = $this->findAndGetColumns($list, $service->getName());

        if (null !== $columns) {
            return $columns[1] === 'enabled';
        }

        return false;
    }

    public function isRunning(ServiceInterface $service): bool
    {
        $output = $this->getStatus($service);

        return str_contains($output, 'Active: active (running)');
    }

    public function stop(ServiceInterface $service): bool
    {
        if ($service instanceof SystemdService) {
            if ($this->isRunning($service)) {
                $this->systemctl('--user', 'stop', $service->getName());
            }

            $this->disable($service);
            $this->unload($service);

            return !$this->isRunning($service);
        }

        return false;
    }

    private function disable(SystemdService $service): void
    {
        if ($this->isEnabled($service)) {
            $this->systemctl('--user', 'disable', $service->getName());
        }
    }

    public function getPid(ServiceInterface $service): ?int
    {
        $output = $this->systemctl('--user', 'show', '--property', 'MainPID', $service->getName());
        $output = str_replace('MainPID=', '', $output);

        $pid = (int)$output;

        if ($pid > 0) {
            return $pid;
        }

        return null;
    }

    public function reload(ServiceInterface $service): bool
    {
        if ($service instanceof SystemdService) {
            $serviceFile = $this->getServiceFile($service);
            $this->filesystem->copy($service->getFilePath(), $serviceFile);

            $this->systemctl('--user', 'daemon-reload');

            return true;
        }

        return false;
    }

    /**
     * @param list<string> $lines
     * @return array<string, string>
     */
    private function parseSection(array $lines): array
    {
        $data = [];
        $continuation = false;
        $key = null;

        foreach ($lines as $line) {
            if (null === $key) {
                $parts = explode('=', $line, 2);

                if (count($parts) === 2) {
                    $key = $parts[0];
                } else {
                    throw new RuntimeException('Expected key.');
                }

                $data[$key] = $parts[1];

                if (str_ends_with($line, '\\')) {
                    $continuation = true;
                } else {
                    $continuation = false;
                    $key = null;
                }
            } else {
                if ($continuation) {
                    $data[$key] .= ' ' . $line;

                    if (str_ends_with($line, '\\')) {
                        $continuation = true;
                    } else {
                        $continuation = false;
                    }
                } else {
                    throw new RuntimeException('Expected key.');
                }
            }
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->unquoteString($value);
        }

        return $data;
    }

    private function unquoteString(string $input): string
    {
        return preg_replace_callback(
            '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/',
            function ($matches) {
                // Remove outer quotes
                $quoted = substr($matches[0], 1, -1);

                // Unescape inner quotes and backslashes
                return str_replace(
                    ['\\"', "\\'", '\\\\'],
                    ['"', "'", '\\'],
                    $quoted
                );
            },
            $input
        );
    }
}