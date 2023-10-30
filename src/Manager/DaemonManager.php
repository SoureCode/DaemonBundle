<?php

namespace SoureCode\Bundle\Daemon\Manager;

use SoureCode\Bundle\Daemon\Adapter\AdapterInterface;
use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class DaemonManager
{
    /**
     * @var array<string, ServiceInterface>|null
     */
    private ?array $services = null;


    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly Filesystem       $filesystem,
        private readonly string           $serviceDirectory,
    )
    {
    }

    public function isRunning(string|ServiceInterface $serviceOrServiceName): bool
    {
        $service = is_string($serviceOrServiceName) ? $this->getService($serviceOrServiceName) : $serviceOrServiceName;

        if (null === $service) {
            return false;
        }

        return $this->adapter->isRunning($service);
    }

    public function getService(string $name): ?ServiceInterface
    {
        $services = $this->getServices();

        return $services[$name] ?? null;
    }

    /**
     * @return array<string, ServiceInterface>
     */
    public function getServices(): array
    {
        if (null === $this->services) {
            if (!$this->filesystem->exists($this->serviceDirectory)) {
                return [];
            }

            $finder = new Finder();

            $finder->files()
                ->in($this->serviceDirectory)
                ->name('*.{service,plist}');

            $serviceFiles = $finder->getIterator();
            $services = [];

            foreach ($serviceFiles as $serviceFile) {
                if (true === $this->adapter->supports($serviceFile)) {
                    $name = $serviceFile->getBasename('.' . $serviceFile->getExtension());

                    $services[$name] = $this->adapter->createService($name, $serviceFile);
                }
            }

            ksort($services);

            $this->services = $services;
        }

        return $this->services;
    }

    public function stopAll(?string $pattern = null): bool
    {
        $stopped = [];

        foreach ($this->getServiceNames() as $name) {
            if (null !== $pattern && !str_contains($name, $pattern)) {
                continue;
            }

            $stopped[] = $this->stop($name);
        }

        return !in_array(false, $stopped, true);
    }

    /**
     * @return list<string>
     */
    public function getServiceNames(): array
    {
        return array_keys($this->getServices());
    }

    public function stop(string|ServiceInterface $serviceOrServiceName): bool
    {
        $service = is_string($serviceOrServiceName) ? $this->getService($serviceOrServiceName) : $serviceOrServiceName;

        if (null === $service) {
            return false;
        }

        return $this->adapter->stop($service);
    }

    public function startAll(?string $pattern = null): bool
    {
        $started = [];

        foreach ($this->getServiceNames() as $name) {
            if (null !== $pattern && !str_contains($name, $pattern)) {
                continue;
            }

            $started[] = $this->start($name);
        }

        return !in_array(false, $started, true);
    }

    public function start(string|ServiceInterface $serviceOrServiceName): bool
    {
        $service = is_string($serviceOrServiceName) ? $this->getService($serviceOrServiceName) : $serviceOrServiceName;

        if (null === $service) {
            return false;
        }

        return $this->adapter->start($service);
    }

    public function reload(): void
    {
        $this->services = null;
    }
}