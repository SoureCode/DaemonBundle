<?php

namespace SoureCode\Bundle\Daemon\Pid;

use Symfony\Component\Process\Process;

class UnmanagedPid
{
    public static array $defaultStopSignals = [
        15, // SIGTERM
        2, // SIGINT
        9, // SIGKILL
    ];

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

    /**
     * Sends multiple signals to the process to stop it gracefully.
     *
     * @param int $timeout Timeout in seconds before sending the next signal.
     * @param array|null $signals Ordered list of signals to send.
     * @return void
     */
    public function gracefullyStop(int $timeout = 10, ?array $signals = null): bool
    {
        if (!$this->isRunning()) {
            $this->value = null;
            return true;
        }

        if (null === $signals) {
            $signals = self::$defaultStopSignals;
        }

        foreach ($signals as $signal) {
            if (!is_int($signal)) {
                throw new \InvalidArgumentException(sprintf('Signal must be an integer, got "%s".', gettype($signal)));
            }

            if ($signal < 1) {
                throw new \InvalidArgumentException(sprintf('Signal must be greater than 0, got "%s".', $signal));
            }
        }

        foreach ($signals as $signal) {
            $this->sendSignal($signal);

            $stopped = $this->wait($timeout);

            if ($stopped) {
                $this->value = null;
                return true;
            }
        }

        // Now we hope that the process is dead, but we cannot be sure, so we assume it failed to stop.
        return false;
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

    private function wait(int $timeout = 10): bool
    {
        $milliseconds = 10;
        $microseconds = $milliseconds * 1000;
        $iterations = $timeout / ($milliseconds / 1000);

        for ($i = 0; $i < $iterations; $i++) {
            if (!$this->isRunning()) {
                return true;
            }

            usleep($microseconds);
        }

        return false;
    }
}