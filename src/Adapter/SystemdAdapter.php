<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SplFileInfo;

class SystemdAdapter implements AdapterInterface
{
    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface
    {
        throw new \Exception("Not implemented yet.");
    }

    public function supports(SplFileInfo $serviceFile): bool
    {
        throw new \Exception("Not implemented yet.");
    }

    public function start(ServiceInterface $service): void
    {
        throw new \Exception("Not implemented yet.");
    }

    public function stop(ServiceInterface $service): void
    {
        throw new \Exception("Not implemented yet.");
    }

    public function restart(ServiceInterface $service): void
    {
        throw new \Exception("Not implemented yet.");
    }

    public function isRunning(ServiceInterface $service): bool
    {
        throw new \Exception("Not implemented yet.");
    }
}