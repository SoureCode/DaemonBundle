<?php

namespace SoureCode\Bundle\Daemon\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\Daemon\Adapter\AdapterInterface;
use SoureCode\Bundle\Daemon\Adapter\SystemdAdapter;
use SoureCode\Bundle\Daemon\Tests\AbstractBaseTest;
use Symfony\Component\Filesystem\Filesystem;

class SystemdAdapterTest extends TestCase
{
    private ?AdapterInterface $adapter = null;
    private ?Filesystem $filesystem = null;

    public function setUp(): void
    {
        AbstractBaseTest::setUpTemplates();
        $this->filesystem = new Filesystem();
        $this->adapter = new SystemdAdapter($this->filesystem);
    }

    public function tearDown(): void
    {
        $this->adapter = null;
        $this->filesystem = null;
        AbstractBaseTest::tearDownTemplates();
    }

    public function testLoadUnloadIsLoaded(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This test is only for linux');
        }

        // Arrange
        $path = __DIR__ . '/../services/example1.service';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);

        // Act
        $this->adapter->load($service);

        // Assert
        $this->assertTrue($this->adapter->isLoaded($service));

        // Act
        $this->adapter->unload($service);

        // Assert
        $this->assertFalse($this->adapter->isLoaded($service));

        // Cleanup
        $this->adapter->unload($service);
    }

    public function testStartStopIsRunning(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This test is only for linux');
        }

        // Arrange
        $path = __DIR__ . '/../services/example1.service';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);

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
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This test is only for linux');
        }

        // Arrange
        $path = __DIR__ . '/../services/example1.service';
        $service = $this->adapter->createService('example1', new \SplFileInfo($path));

        // Cleanup
        $this->adapter->stop($service);
        $this->adapter->unload($service);

        // Act
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