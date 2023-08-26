<?php

namespace SoureCode\Bundle\Daemon\Tests;

use SoureCode\Bundle\Daemon\Command\DaemonCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Path;

class DaemonTest extends AbstractBaseTest
{
    public function testExecution(): void
    {
        // Arrange
        if (!self::$booted) {
            self::bootKernel();
        }

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $random = random_int(0, 1000000);
        $value = "test: " . $random;

        $applicationTester = new ApplicationTester($application);

        // Act
        $applicationTester->run([
            'command' => DaemonCommand::getDefaultName(),
            '-vvv',
            '--id' => 'daemon-test-test-execution',
            '--no-auto-restart' => true,
            'process' => [
                Path::join(__DIR__, 'daemons', 'short-running.sh'),
                $value,
            ],
        ]);

        // Assert
        $applicationTester->assertCommandIsSuccessful();

        $output = $applicationTester->getDisplay();

        $this->assertStringContainsString($value, $output);
    }

    // test auto restart
    // how to stop execution?
}
