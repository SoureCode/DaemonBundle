<?php

namespace SoureCode\Bundle\Daemon\Manager;

use InvalidArgumentException;
use SoureCode\Bundle\Daemon\Adapter\AdapterInterface;
use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use const DIRECTORY_SEPARATOR;

#[Autoconfigure(tags: ['monolog.logger' => 'daemon'])]
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

    public function start(string|ServiceInterface $service): void
    {
        if (is_string($service)) {
            $service = $this->getService($service);
        }

        $this->adapter->start($service);
    }

    public function isRunning(string|ServiceInterface $service): bool
    {
        if (is_string($service)) {
            $service = $this->getService($service);
        }

        return $this->adapter->isRunning($service);
    }


    public function stopAll(?string $pattern = null): void
    {
        foreach ($this->getServiceNames() as $name) {
            if (null !== $pattern && !str_contains($name, $pattern)) {
                continue;
            }

            $this->stop($name);
        }
    }

    public function stop(string|ServiceInterface $service): void
    {
        if (is_string($service)) {
            $service = $this->getService($service);
        }

        $this->adapter->stop($service);
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
                    $relativePath = $serviceFile->getRelativePath();
                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '.', $relativePath);

                    $name = $relativePath . '.' . $serviceFile->getBasename('.' . $serviceFile->getExtension());
                    $name = ltrim($name, '.');

                    $services[$name] = $this->adapter->createService($name, $serviceFile);
                }
            }

            ksort($services);

            $this->services = $services;
        }

        return $this->services;
    }

    /**
     * @return list<string>
     */
    public function getServiceNames(): array
    {
        return array_keys($this->getServices());
    }

    public function getService(string $name): ServiceInterface
    {
        $services = $this->getServices();

        if (!array_key_exists($name, $services)) {
            throw new InvalidArgumentException(sprintf('Service "%s" not found.', $name));
        }

        return $services[$name];
    }
}