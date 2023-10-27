<?php

namespace SoureCode\Bundle\Daemon\Tests;

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
}
