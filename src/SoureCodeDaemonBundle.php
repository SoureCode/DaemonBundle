<?php

namespace SoureCode\Bundle\Daemon;

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
                    ->defaultValue('systemd')
                    ->info('The adapter to use. Currently only systemd and launchd are supported.')
                ->end()
            ->end();
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('soure_code_daemon.service_directory', $config['directory']);

        $services = $container->services();

        $services->set('soure_code_daemon.adapter.systemd', Adapter\SystemdAdapter::class);
        $services->set('soure_code_daemon.adapter.launchd', Adapter\LaunchdAdapter::class);

        $services->set('soure_code_daemon.daemon_manager', DaemonManager::class)
            ->args([
                service('soure_code_daemon.adapter.' . $config['adapter']),
                param('soure_code_daemon.service_directory'),
            ]);

        $services->alias(DaemonManager::class, 'soure_code_daemon.daemon_manager')
            ->public();

        $services->set('soure_code_daemon.command.daemon.start', DaemonStartCommand::class)
            ->args([
                service('soure_code_daemon.daemon_manager'),
            ])
            ->public();

        $services->set('soure_code_daemon.command.daemon.stop', DaemonStopCommand::class)
            ->args([
                service('soure_code_daemon.daemon_manager'),
            ])
            ->public();

    }
}