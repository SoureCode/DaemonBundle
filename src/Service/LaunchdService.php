<?php

namespace SoureCode\Bundle\Daemon\Service;

class LaunchdService implements ServiceInterface
{
    public function __construct(
        private readonly string       $name,
        private readonly \SplFileInfo $file,
        private readonly string       $contents,
        private readonly array        $config,
    )
    {
    }

    public function getLabel(): string
    {
        return $this->config['Label'];
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