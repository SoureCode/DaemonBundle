<?php

namespace SoureCode\Bundle\Daemon\Pid;

use Symfony\Component\Process\Process;

class UnmanagedPid
{
    /**
     * The process id.
     */
    private ?int $value = null;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public static function fromProcess(Process $process): self
    {
        return new self($process->getPid());
    }

    public static function fromGlobals(): self
    {
        return new self(getmypid());
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function isRunning(): bool
    {
        if (null === $this->value) {
            return false;
        }

        return $this->sendSignal(0);
    }

    public function sendSignal(int $signal): bool
    {
        if (null === $this->value) {
            return false;
        }

        return posix_kill($this->value, $signal);
    }

    public function gracefullyStop(int $timeout = 10): void
    {
        if (!$this->isRunning()) {
            $this->value = null;
            return;
        }

        $this->sendSignal(2); // SIGINT

        $stopped = $this->wait($timeout);

        if ($stopped) {
            $this->value = null;
            return;
        }

        $this->sendSignal(15); // SIGTERM

        $stopped = $this->wait($timeout);

        if ($stopped) {
            $this->value = null;
            return;
        }

        if ($this->isRunning()) {
            $this->sendSignal(9); // SIGKILL

            // Now we hope that the process is dead.
            // Long live the process.
            $this->value = null;
        }
    }

    private function wait(int $timeout = 10): bool
    {
        $milliseconds = 10;
        $microseconds = $milliseconds * 1000;
        $iterations = $timeout * (1000 / $milliseconds);

        for ($i = 0; $i < $iterations; $i++) {
            if (!$this->isRunning()) {
                return true;
            }

            usleep($microseconds);
        }

        return false;
    }
}