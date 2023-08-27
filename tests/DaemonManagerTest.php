<?php

namespace SoureCode\Bundle\Daemon\Tests;

use RuntimeException;
use SoureCode\Bundle\Daemon\Command\DaemonStartCommand;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Path;

class DaemonManagerTest extends AbstractBaseTest
{
    public function testStart(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        $id = 'daemon-start-test-test-start';
        $process = Path::join(__DIR__, 'daemons', 'long-running.sh');

        // Act
        $daemonManager->start($id, $process);

        // Assert
        try {
            $this->assertProcessExists('long-running.sh');

            // Cleanup
            $daemonManager->stop($id);

            $this->assertProcessNotExists('long-running.sh');
        } finally {
            if (!$daemonManager->isRunning($id)) {
                $daemonManager->stop($id);
            }
        }
    }

    public function testBootError(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        $id = 'daemon-start-test-test-boot-error';
        $process = Path::join(__DIR__, 'daemons', 'error-running.sh');

        // Pre Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Daemon exited with an error");

        // Act
        $daemonManager->start($id, $process);
    }
}
