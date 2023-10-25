<?php

namespace SoureCode\Bundle\Daemon\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\Daemon\Adapter\AdapterInterface;
use SoureCode\Bundle\Daemon\Adapter\LaunchdAdapter;

class LaunchdAdapterTest extends TestCase
{
    private ?AdapterInterface $adapter = null;

    public function setUp(): void
    {
        $this->adapter = new LaunchdAdapter();
    }

    public function tearDown(): void
    {
        $this->adapter = null;
    }

    public function testLoadUnloadIsLoaded(): void
    {
        // Arrange
        $path = __DIR__ . '/../services/example1.plist';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Act
        $this->adapter->load($service);

        // Assert
        $this->assertTrue($this->adapter->isLoaded($service));

        // Act
        $this->adapter->unload($service);

        // Assert
        $this->assertFalse($this->adapter->isLoaded($service));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);
    }

    public function testStartStopIsRunning(): void
    {
        // Arrange
        $path = __DIR__ . '/../services/example1.plist';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Act
        $this->adapter->start($service);

        // Assert
        $this->assertTrue($this->adapter->isRunning($service));

        // Act
        $this->adapter->stop($service);

        // Assert
        $this->assertFalse($this->adapter->isRunning($service));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);
    }

    public function testRestart(): void
    {
        // Arrange
        $path = __DIR__ . '/../services/example1.plist';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        $this->adapter->start($service);

        $pid = $this->adapter->getPid($service);

        // Act
        $this->adapter->restart($service);

        // Assert
        $this->assertNotEquals($pid, $this->adapter->getPid($service));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);
    }
}