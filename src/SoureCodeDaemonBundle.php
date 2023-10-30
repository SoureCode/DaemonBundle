<?php

namespace SoureCode\Bundle\Daemon;

use RuntimeException;
use SoureCode\Bundle\Daemon\Command\DaemonStartCommand;
use SoureCode\Bundle\Daemon\Command\DaemonStopCommand;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SoureCodeDaemonBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->children()
                ->scalarNode('directory')
                    ->defaultValue('%kernel.project_dir%/etc/daemons')
                    ->info('The directory where the daemons are located. The daemons must be named <name>.service for systemd or <name>.plist for launchd.')
                ->end()
                ->scalarNode('adapter')
                    ->defaultValue('auto')
                    ->info('The adapter to use. Currently only systemd and launchd are supported.')
                    ->validate()
                        ->ifNotInArray(['systemd', 'launchd', 'auto'])
                        ->thenInvalid('Invalid adapter "%s".')
                    ->end()
                ->end()
            ->end();
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('soure_code.daemon.directory', $config['directory']);

        $services = $container->services();

        $services->set('soure_code.daemon.adapter.systemd', Adapter\SystemdAdapter::class)
            ->args([
                service('filesystem'),
            ]);

        $services->set('soure_code.daemon.adapter.launchd', Adapter\LaunchdAdapter::class);

        $adapter = $config['adapter'];

        if ($adapter === 'auto') {
            if (PHP_OS_FAMILY === 'Linux') {
                $adapter = 'systemd';
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                $adapter = 'launchd';
            } else {
                throw new RuntimeException('Could not detect adapter.');
            }
        }

        $services->set('soure_code.daemon.daemon_manager', DaemonManager::class)
            ->args([
                service('soure_code.daemon.adapter.' . $adapter),
                service('filesystem'),
                param('soure_code.daemon.directory'),
            ]);

        $services->alias(DaemonManager::class, 'soure_code.daemon.daemon_manager')
            ->public();

        $services->set('soure_code.daemon.command.daemon.start', DaemonStartCommand::class)
            ->args([
                service('soure_code.daemon.daemon_manager'),
            ])
            ->public()
            ->tag('console.command', ['command' => 'daemon:start']);

        $services->set('soure_code.daemon.command.daemon.stop', DaemonStopCommand::class)
            ->args([
                service('soure_code.daemon.daemon_manager'),
            ])
            ->public()
            ->tag('console.command', ['command' => 'daemon:stop']);

    }
}