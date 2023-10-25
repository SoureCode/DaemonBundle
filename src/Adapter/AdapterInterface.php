<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SplFileInfo;

interface AdapterInterface
{
    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface;

    public function supports(SplFileInfo $serviceFile): bool;

    public function start(ServiceInterface $service): void;

    public function stop(ServiceInterface $service): void;

    public function restart(ServiceInterface $service): void;

    public function isRunning(ServiceInterface $service): bool;
}