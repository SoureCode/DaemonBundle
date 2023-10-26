<?php

namespace SoureCode\Bundle\Daemon\Service;

class SystemdService implements ServiceInterface
{
    public function __construct(
        private readonly string       $name,
        private readonly \SplFileInfo $file,
        private readonly array        $config,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFilePath(): string
    {
        return $this->file->getPathname();
    }
}