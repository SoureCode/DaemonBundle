<?php

namespace SoureCode\Bundle\Daemon\Service;

interface ServiceInterface
{
    public function getName(): string;

    public function getFilePath(): string;
}