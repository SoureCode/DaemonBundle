<?php

namespace SoureCode\Bundle\Daemon\Tests;

use Monolog\LogRecord;
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
            $stopped = $daemonManager->stop($id);

            $this->assertTrue($stopped);
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
}
