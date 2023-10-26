<?php

namespace SoureCode\Bundle\Daemon\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Daemon\SoureCodeDaemonBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractBaseTest extends KernelTestCase
{
    public static function setUpTemplates(): void
    {
        $projectDirectory = realpath(__DIR__ . '/..').'/';
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/services')->name('*.template');
        $files = $finder->getIterator();

        foreach ($files as $file) {
            $content = str_replace('{PROJECT_DIRECTORY}', $projectDirectory, $file->getContents());
            $target = str_replace('.template', '', $file->getRealPath());

            file_put_contents($target, $content);
        }
    }

    public static function tearDownTemplates(): void
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/services')->notName('*.template');
        $files = $finder->getIterator();

        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }

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
}