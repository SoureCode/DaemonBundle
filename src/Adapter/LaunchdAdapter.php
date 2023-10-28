<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use RuntimeException;
use SimpleXMLElement;
use SoureCode\Bundle\Daemon\Service\LaunchdService;
use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SplFileInfo;
use Symfony\Component\Process\Process;

class LaunchdAdapter extends AbstractAdapter
{
    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface
    {
        $contents = file_get_contents($serviceFile->getPathname());
        $config = $this->parseFile($contents);

        return new LaunchdService($name, $serviceFile, $config);
    }

    private function parseFile(string $contents): array
    {
        $xml = simplexml_load_string($contents);

        if (false === $xml) {
            throw new RuntimeException('Could not parse xml.');
        }

        return $this->parseDict($xml->children());
    }

    private function parseDict(SimpleXMLElement $dict): array
    {
        if ('dict' !== $dict->getName()) {
            throw new RuntimeException('Expected dict.');
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
                throw new RuntimeException('Expected key.');
            }
        }

        return $data;
    }

    private function parseValue(SimpleXMLElement $child): array|bool|int|string
    {
        $name = $child->getName();

        return match ($name) {
            'dict' => $this->parseDict($child),
            'string' => (string)$child,
            'integer' => (int)$child,
            'array' => $this->parseArray($child),
            'true' => true,
            'false' => false,
        };
    }

    private function parseArray(SimpleXMLElement $child): array
    {
        $data = [];

        foreach ($child->children() as $item) {
            $data[] = $this->parseValue($item);
        }

        return $data;
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

    public function start(ServiceInterface $service): bool
    {
        if ($service instanceof LaunchdService) {
            $this->load($service);

            if ($this->isRunning($service)) {
                return true;
            }

            $this->launchctl('start', $service->getLabel());

            return $this->isRunning($service);
        }

        return false;
    }

    private function load(LaunchdService $service): void
    {
        // Unload if loaded to ensure that the service is loaded with the latest configuration.
        if ($this->isLoaded($service)) {
            $this->unload($service);
        }

        $this->launchctl('load', '-w', $service->getFilePath());
    }

    private function isLoaded(LaunchdService $service): bool
    {
        $output = $this->launchctl('list');

        return str_contains($output, $service->getLabel());
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

    private function unload(LaunchdService $service): void
    {
        if (!$this->isLoaded($service)) {
            return;
        }

        $this->launchctl('unload', '-w', $service->getFilePath());
    }

    public function isRunning(ServiceInterface $service): bool
    {
        return null !== $this->getPid($service);
    }

    public function getPid(ServiceInterface $service): ?int
    {
        if ($service instanceof LaunchdService) {
            $output = $this->launchctl('list');
            $columns = $this->findAndGetColumns($output, $service->getLabel());

            if (null !== $columns) {
                $pidColumn = $columns[0];

                if (is_numeric($pidColumn)) {
                    return (int)$pidColumn;
                }

                return null;
            }
        }

        return null;
    }

    public function stop(ServiceInterface $service): bool
    {
        if ($service instanceof LaunchdService) {
            if (!$this->isRunning($service)) {
                return true;
            }

            $this->launchctl('stop', $service->getLabel());

            $this->unload($service);

            return !$this->isRunning($service);
        }

        return false;
    }
}