<?php

namespace SoureCode\Bundle\Daemon\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\Daemon\Adapter\AdapterInterface;
use SoureCode\Bundle\Daemon\Adapter\LaunchdAdapter;
use SoureCode\Bundle\Daemon\Tests\AbstractBaseTest;

class LaunchdAdapterTest extends TestCase
{
    private ?AdapterInterface $adapter = null;

    public function setUp(): void
    {
        AbstractBaseTest::setUpTemplates();
        $this->adapter = new LaunchdAdapter();
    }

    public function tearDown(): void
    {
        $this->adapter = null;
        AbstractBaseTest::tearDownTemplates();
    }

    public function testStartStopIsRunning(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('This test is only for darwin');
        }

        // Arrange
        $path = __DIR__ . '/../services/example1.plist';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Act
        $this->assertTrue($this->adapter->start($service));

        // Assert
        $this->assertTrue($this->adapter->isRunning($service));

        // Act
        $this->assertTrue($this->adapter->stop($service));

        // Assert
        $this->assertFalse($this->adapter->isRunning($service));

        // Cleanup
        $this->assertTrue($this->adapter->stop($service));
    }

    public function testRestart(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('This test is only for darwin');
        }

        // Arrange
        $path = __DIR__ . '/../services/example1.plist';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        $this->assertTrue($this->adapter->start($service));

        $pid = $this->adapter->getPid($service);

        // Act
        $this->assertTrue($this->adapter->restart($service));

        // Assert
        $this->assertNotEquals($pid, $this->adapter->getPid($service));

        // Cleanup
        $this->assertTrue($this->adapter->stop($service));
    }
}