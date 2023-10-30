<?php

namespace SoureCode\Bundle\Daemon\Tests;

class BundleInitializationTest extends AbstractBaseTest
{
    public function testInitBundle(): void
    {
        // Get the test container
        $container = self::getContainer();

        // Test if your services exists
        $this->assertTrue($container->has('soure_code.daemon.command.daemon.start'), 'DaemonStartCommand is registered');
        $this->assertTrue($container->has('soure_code.daemon.command.daemon.stop'), 'DaemonStopCommand is registered');
    }
}
