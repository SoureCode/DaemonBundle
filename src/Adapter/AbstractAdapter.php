<?php

namespace SoureCode\Bundle\Daemon\Adapter;

use SoureCode\Bundle\Daemon\Service\ServiceInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    public function findLine(string $output, string $text): ?string
    {
        $lines = preg_split('/\r?\n/', $output);

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_contains($line, $text)) {
                return $line;
            }
        }

        return null;
    }

    public function findAndGetColumns(string $output, string $text): ?array
    {
        $line = $this->findLine($output, $text);

        if (null === $line) {
            return null;
        }

        return preg_split('/\s+/', $line);
    }

    public function restart(ServiceInterface $service): void
    {
        $this->stop($service);
        $this->start($service);
    }
}