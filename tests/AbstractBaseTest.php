<?php

namespace SoureCode\Bundle\Daemon\Tests;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Daemon\SoureCodeDaemonBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractBaseTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(MonologBundle::class);
        $kernel->addTestBundle(SoureCodeDaemonBundle::class);
        $kernel->setTestProjectDir(__DIR__);
        $kernel->addTestConfig(Path::join(__DIR__, 'config.yaml'));
        $kernel->handleOptions($options);

        return $kernel;
    }

    protected function assertProcessExists(string $process): void
    {
        $processes = $this->getProcesses();
        $processList = implode(PHP_EOL, $processes);

        $this->assertStringContainsString($process, $processList);
    }

    protected function getProcesses(): array
    {
        $whoami = exec('whoami');
        $command = 'ps -f -u ' . $whoami . ' 2>&1';

        $output = [];
        exec($command, $output);

        return array_values(array_map('trim', $output));
    }

    protected function assertProcessNotExists(string $process): void
    {
        $processes = $this->getProcesses();
        $processList = implode(PHP_EOL, $processes);

        $this->assertStringNotContainsString($process, $processList);
    }

    protected function getTestHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.testing');
    }

    protected function hasRecordThatMatches(string $pattern): bool
    {
        $testHandler = $this->getTestHandler();

        $records = array_map(
            static fn(LogRecord $record) => $record->formatted,
            $testHandler->getRecords()
        );

        $pattern = preg_quote($pattern, '/');

        foreach ($records as $record) {
            if (preg_match('/' . $pattern . '/im', $record)) {
                return true;
            }
        }

        return false;
    }
}