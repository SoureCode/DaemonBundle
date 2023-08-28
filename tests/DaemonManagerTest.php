<?php

namespace SoureCode\Bundle\Daemon\Tests;

use SoureCode\Bundle\Daemon\Manager\DaemonManager;
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
        $started = $daemonManager->start($id, $process);

        // Assert
        try {
            $this->assertTrue($started);
            $this->assertProcessExists('long-running.sh');

            // Cleanup
            $stopped = $daemonManager->stop($id, 1);

            $this->assertTrue($stopped);
            $this->assertProcessNotExists('long-running.sh');
        } finally {
            $daemonManager->stop($id, 1, [9]);
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

        // Act
        $started = $daemonManager->start($id, $process . " test");

        // Assert
        $this->assertFalse($started, 'Daemon should result in false.');
        $this->assertTrue($this->hasRecordThatMatches("Daemon command output contains error keyword."));
        $this->assertTrue($this->hasRecordThatMatches("Exception: test"));
    }

    public function testCrash(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        $id = 'daemon-start-test-test-crash';
        $process = Path::join(__DIR__, 'daemons', 'crash-running.sh');

        // Act
        $started = $daemonManager->start($id, $process);

        // Assert
        $this->assertFalse($started, 'Daemon should result in false.');
        $this->assertTrue($this->hasRecordThatMatches("Daemon crashed after start."));
        $this->assertTrue($this->hasRecordThatMatches("Something what does not contain a keyword"));
    }

    public function testStopAllWithPattern(): void
    {
        // Arrange
        $container = self::getContainer();

        /**
         * @var DaemonManager $daemonManager
         */
        $daemonManager = $container->get(DaemonManager::class);

        $process = Path::join(__DIR__, 'daemons', 'long-running.sh');

        $daemonManager->start("daemon-start-test-test-stop-all-1", $process);
        $daemonManager->start("daemon-start-test-test-stop-all-2", $process);

        sleep(1);

        $pid1 = $daemonManager->pid("daemon-start-test-test-stop-all-1");
        $pid2 = $daemonManager->pid("daemon-start-test-test-stop-all-2");

        try {
            $this->assertTrue($pid1->isRunning());
            $this->assertTrue($pid2->isRunning());

            // Act
            $daemonManager->stopAll('/2$/', 1);

            // Assert
            $this->assertTrue($pid1->isRunning());
            $this->assertFalse($pid2->isRunning());
        } finally {
            $daemonManager->stopAll(null, 1);
        }
    }
}
