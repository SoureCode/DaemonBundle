<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use SoureCode\Bundle\Daemon\Service\LaunchdService;
use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SplFileInfo;
use Symfony\Component\Process\Process;

class LaunchdAdapter implements AdapterInterface
{
    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface
    {
        $contents = file_get_contents($serviceFile->getPathname());
        $config = $this->parseFile($contents);

        return new LaunchdService($name, $serviceFile, $contents, $config);
    }

    private function parseFile(string $contents): array
    {
        $xml = simplexml_load_string($contents);

        if (false === $xml) {
            throw new \RuntimeException('Could not parse xml.');
        }

        return $this->parseDict($xml->children());
    }

    private function parseDict(\SimpleXMLElement $dict): array
    {
        if ('dict' !== $dict->getName()) {
            throw new \RuntimeException('Expected dict.');
        }

        $data = [];
        $key = null;

        foreach ($dict->children() as $child) {
            if (null === $key && $child->getName() === "key") {
                $key = (string)$child;
            } else if (null !== $key) {
                $data[$key] = $this->parseValue($child);
                $key = null;
            } else {
                throw new \RuntimeException('Expected key.');
            }
        }

        return $data;
    }

    private function parseValue(\SimpleXMLElement $child): array|bool|int|string
    {
        $name = $child->getName();

        return match ($name) {
            'dict' => $this->parseDict($child),
            'string' => (string)$child,
            'integer' => (int)$child,
            'true' => true,
            'false' => false,
        };
    }

    public function supports(SplFileInfo $serviceFile): bool
    {
        if (false === $serviceFile->isFile()) {
            return false;
        }

        if (false === $serviceFile->isReadable()) {
            return false;
        }

        if ('plist' !== $serviceFile->getExtension()) {
            return false;
        }

        return true;
    }

    /**
     * @param string ...$args
     * @return string
     */
    private function launchctl(...$args): string
    {
        $process = new Process([
            'launchctl',
            ...$args,
        ]);

        $process->mustRun();

        return $process->getOutput();
    }

    private function findLine(string $output, string $text): ?string
    {
        $lines = preg_split('/\r?\n/', $output);

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_contains($line, $text)) {
                return $line;
            }
        }

        return null;
    }

    public function start(ServiceInterface $service): void
    {
        if ($service instanceof LaunchdService) {
            $this->load($service);

            if (!$this->isRunning($service)) {
                $this->launchctl('start', $service->getLabel());
            }
        }
    }

    public function stop(ServiceInterface $service): void
    {
        if ($service instanceof LaunchdService) {
            if ($this->isRunning($service)) {
                $this->launchctl('stop', $service->getLabel());
            }

            $this->unload($service);
        }
    }

    public function restart(ServiceInterface $service): void
    {
        if ($service instanceof LaunchdService) {
            $this->stop($service);
            $this->start($service);
        }
    }

    public function load(ServiceInterface $service): void
    {
        if ($service instanceof LaunchdService) {
            if ($this->isLoaded($service)) {
                return;
            }

            $this->launchctl('load', '-w', $service->getFilePath());
        }
    }

    public function unload(ServiceInterface $service): void
    {
        if ($service instanceof LaunchdService) {
            if (!$this->isLoaded($service)) {
                return;
            }

            $this->launchctl('unload', '-w', $service->getFilePath());
        }
    }

    public function isLoaded(ServiceInterface $service): bool
    {
        if ($service instanceof LaunchdService) {
            $output = $this->launchctl('list');

            return str_contains($output, $service->getLabel());
        }

        return false;
    }

    public function isRunning(ServiceInterface $service): bool
    {
        return null !== $this->getPid($service);
    }

    public function getPid(ServiceInterface $service): ?int
    {
        if ($service instanceof LaunchdService) {
            $output = $this->launchctl('list');
            $line = $this->findLine($output, $service->getLabel());

            if (null !== $line) {
                $columns = preg_split('/\s+/', $line);

                return (int)$columns[0];
            }
        }

        return null;
    }
}