<?php

namespace SoureCode\Bundle\Daemon\Pid;

use RuntimeException;
use Stringable;
use Symfony\Component\Filesystem\Path;

class ManagedPid implements Stringable
{
    /**
     * The directory where the pid file is stored.
     */
    private string $directory;

    /**
     * The process id.
     */
    private ?UnmanagedPid $pid = null;

    /**
     * The daemon id.
     */
    private string $id;

    public function __construct(
        string $directory,
        string $id,
        ?int   $value = null
    )
    {
        $this->directory = $directory;
        $this->id = $id;

        $this->init($value);
    }

    private function init(?int $value): void
    {
        if ($value === null) {
            $filePath = $this->getFilePath();

            if (file_exists($filePath)) {
                $this->init((int)file_get_contents($filePath));
            } else {
                $this->pid = null;
            }
        } else {
            $this->setPid(new UnmanagedPid($value));
        }
    }

    public function getFilePath(): string
    {
        $fileName = $this->getFileName();

        return Path::join($this->directory, $fileName);
    }

    public function getFileName(): string
    {
        return sprintf("%s.pid", $this->getHash());
    }

    public function getHash(): string
    {
        return hash('sha256', $this->id);
    }

    public function getPid(): ?UnmanagedPid
    {
        return $this->pid;
    }

    public function setPid(?UnmanagedPid $pid): void
    {
        $this->pid = $pid;

        if (null === $pid) {
            $this->remove();
        } else {
            $this->dump();
        }
    }

    public function getIdFilePath(): string
    {
        $fileName = $this->getIdFileName();

        return Path::join($this->directory, $fileName);
    }

    public function getIdFileName(): string
    {
        return sprintf("%s.id", $this->getHash());
    }

    public function dump(): void
    {
        if (null === $this->pid) {
            return;
        }

        if (!file_exists($this->directory)) {
            $directory = $this->directory;

            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" could not be created.', $directory));
            }
        }

        $filePath = $this->getFilePath();
        $idFilePath = $this->getIdFilePath();

        file_put_contents($filePath, $this->pid->getValue());
        file_put_contents($idFilePath, $this->id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function exists(): bool
    {
        return file_exists($this->getFilePath());
    }

    public function isRunning(): bool
    {
        return $this->sendSignal(0);
    }

    public function sendSignal(int $signal): bool
    {
        $this->reload();

        if (null === $this->pid) {
            return false;
        }

        return $this->pid->sendSignal($signal);
    }

    public function reload(): void
    {
        $this->init(null);
    }

    public function dumpExitFile(): void
    {
        $filePath = $this->getExitFilePath();

        file_put_contents($filePath, '1');
    }

    public function getExitFilePath(): string
    {
        $fileName = $this->getExitFileName();

        return Path::join($this->directory, $fileName);
    }

    public function getExitFileName(): string
    {
        return sprintf("%s.exit", $this->getHash());
    }

    public function willProcessExit(): bool
    {
        $filePath = $this->getExitFilePath();

        return file_exists($filePath);
    }

    /**
     * @param int $timeout Timeout in seconds before sending the next signal.
     * @param array|null $signals Ordered list of signals to send.
     * @return bool true if the process is stopped, false otherwise.
     */
    public function stop(int $timeout = 10, ?array $signals = null): bool
    {
        $this->reload();

        if (null === $this->pid) {
            return false;
        }

        return $this->pid->gracefullyStop($timeout, $signals);
    }

    public function remove(): void
    {
        $filePath = $this->getFilePath();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $idFilePath = $this->getIdFilePath();

        if (file_exists($idFilePath)) {
            unlink($idFilePath);
        }

        $exitFilePath = $this->getExitFilePath();

        if (file_exists($exitFilePath)) {
            unlink($exitFilePath);
        }
    }

    public function __toString()
    {
        if (null === $this->pid) {
            return 'dpid("' . $this->id . '", null)';
        }

        $value = $this->pid->getValue();

        if (null === $value) {
            return 'pid("' . $this->id . '", null)';
        }

        return 'dpid("' . $this->id . '", ' . $value . ')';
    }
}