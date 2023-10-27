<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use SoureCode\Bundle\Daemon\Service\ServiceInterface;
use SplFileInfo;

interface AdapterInterface
{
    /**
     * Creates a service.
     *
     * @param string $name
     * @param SplFileInfo $serviceFile
     * @return ServiceInterface The created service
     */
    public function createService(string $name, SplFileInfo $serviceFile): ServiceInterface;

    /**
     * Returns whether the adapter supports the service file.
     *
     * @param SplFileInfo $serviceFile
     * @return bool True if the adapter supports the service file, false otherwise
     */
    public function supports(SplFileInfo $serviceFile): bool;

    /**
     * Starts a service.
     *
     * @param ServiceInterface $service
     * @return bool True if the service was started or was already running, false if it failed to start
     */
    public function start(ServiceInterface $service): bool;

    /**
     * Stops a service.
     *
     * @param ServiceInterface $service
     * @return bool True if the service was stopped or was already stopped, false if it failed to stop
     */
    public function stop(ServiceInterface $service): bool;

    /**
     * Restarts a service.
     *
     * @param ServiceInterface $service
     * @return bool True if the service was restarted, false if it failed to restart
     */
    public function restart(ServiceInterface $service): bool;

    /**
     * Returns whether a service is running.
     *
     * @param ServiceInterface $service
     * @return bool True if the service is running, false otherwise
     */
    public function isRunning(ServiceInterface $service): bool;
}