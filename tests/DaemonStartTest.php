<?php

namespace SoureCode\Bundle\Daemon\Tests;

use SoureCode\Bundle\Daemon\Command\DaemonStartCommand;
use SoureCode\Bundle\Daemon\Command\DaemonStopCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Path;

class DaemonStartTest extends AbstractBaseTest
{
    public function testStart(): void
    {
        // Arrange
        if (!self::$booted) {
            self::bootKernel();
        }

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $applicationTester = new ApplicationTester($application);
        $id = 'daemon-start-test-test-start';

        // Act
        $applicationTester->run([
            'command' => DaemonStartCommand::getDefaultName(),
            '-vvv' => true,
            '--id' => $id,
            'process' => [
                Path::join(__DIR__, 'daemons', 'long-running.sh'),
            ],
        ]);

        // Assert
        $applicationTester->assertCommandIsSuccessful();

        $display = $applicationTester->getDisplay();

        $this->assertStringContainsString('Daemon started', $display);

        $this->assertProcessExists('long-running.sh');

        // Stop is tested with this as well...
        // Cleanup
        $applicationTester->run([
            'command' => DaemonStopCommand::getDefaultName(),
            '-vvv' => true,
            '--id' => $id,
        ]);

        $this->assertProcessNotExists('long-running.sh');
    }

    public function testBootError(): void
    {
        // Arrange
        if (!self::$booted) {
            self::bootKernel();
        }

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $applicationTester = new ApplicationTester($application);

        // Act
        $applicationTester->run([
            'command' => DaemonStartCommand::getDefaultName(),
            '-vvv',
            '--id' => 'daemon-start-test-test-boot-error',
            'process' => [
                Path::join(__DIR__, 'daemons', 'error-running.sh'),
                "foo"
            ],
        ]);

        // Assert
        self::assertSame(1, $applicationTester->getStatusCode(), "Expected exit code 1");
        self::assertStringContainsString('Exception: foo', $applicationTester->getDisplay(), "Expected exception message");
    }
}
