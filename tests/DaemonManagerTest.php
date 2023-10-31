<?php

namespace SoureCode\Bundle\Daemon\Tests;

use SoureCode\Bundle\Daemon\Adapter\SystemdAdapter;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;

class DaemonManagerTest extends AbstractBaseTest
{
    public function setUp(): void
    {
        AbstractBaseTest::setUpTemplates();
    }

    public function tearDown(): void
    {
        AbstractBaseTest::tearDownTemplates();
    }

    public function testGetServices(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        // Act
        $services = $daemonManager->getServices();

        self::assertCount(1, $services);
        self::assertArrayHasKey('example1', $services);
    }

    public function testGetService(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        // Act
        $service = $daemonManager->getService('example1');

        // Assert
        self::assertNotNull($service);
    }

    public function testGetServiceNames(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        // Act
        $serviceNames = $daemonManager->getServiceNames();

        // Assert
        self::assertCount(1, $serviceNames);
        self::assertContains('example1', $serviceNames);
    }

    public function testOperationalMethods(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        // Assert
        self::assertFalse($daemonManager->isRunning('example1'));

        // Act
        self::assertTrue($daemonManager->start('example1'));

        // Assert
        self::assertTrue($daemonManager->isRunning('example1'));

        // Act
        self::assertTrue($daemonManager->stop('example1'));

        // Assert
        self::assertFalse($daemonManager->isRunning('example1'));

        // Act
        self::assertTrue($daemonManager->start('example1'));

        // Assert
        self::assertTrue($daemonManager->isRunning('example1'));

        // Act
        self::assertTrue($daemonManager->stopAll('bar'));

        // Assert
        self::assertTrue($daemonManager->isRunning('example1'));

        // Act
        self::assertTrue($daemonManager->stopAll('example'));

        // Assert
        self::assertFalse($daemonManager->isRunning('example1'));

        // Arrange
        self::assertTrue($daemonManager->start('example1'));

        // Act
        self::assertTrue($daemonManager->stopAll());

        // Assert
        self::assertFalse($daemonManager->isRunning('example1'));
    }

    public function testReload(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This test is only for linux');
        }

        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);
        /**
         * @var SystemdAdapter $adapter
         */
        $adapter = $container->get('soure_code.daemon.adapter.systemd');
        $service = $daemonManager->getService('example1');

        try {
            self::assertTrue($daemonManager->start($service));
            $pid = $adapter->getPid($service);
            $status = $adapter->getStatus($service);
            $serviceFilePath = $service->getFilePath();

            self::assertStringContainsString('long-running.sh', $status);

            // modify the service file and change the word "long-running.sh" to "another-long-running.sh"
            $serviceFileContent = file_get_contents($serviceFilePath);
            $serviceFileContent = str_replace('long-running.sh', 'another-long-running.sh', $serviceFileContent);
            file_put_contents($serviceFilePath, $serviceFileContent);

            self::assertSame($pid, $adapter->getPid($service));
            self::assertStringContainsString('long-running.sh', $adapter->getStatus($service));

            // reload service
            self::assertTrue($daemonManager->reload($service));

            // validate that the service is still running
            self::assertTrue($adapter->isRunning($service));
            self::assertSame($pid, $adapter->getPid($service));
            self::assertStringContainsString('long-running.sh', $adapter->getStatus($service));

            // Restart
            self::assertTrue($daemonManager->restart($service));

            self::assertNotSame($pid, $adapter->getPid($service));
            self::assertStringContainsString('another-long-running.sh', $adapter->getStatus($service));
        } finally {
            self::assertTrue($daemonManager->stopAll());
        }
    }
}
