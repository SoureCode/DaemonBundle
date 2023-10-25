<?php

namespace SoureCode\Bundle\Daemon\Service;

class SystemdService implements ServiceInterface
{
    public function getName(): string
    {
        throw new \Exception("Not implemented yet.");
    }

    public function getFilePath(): string
    {
        throw new \Exception("Not implemented yet.");
    }
}