<?php

namespace SoureCode\Bundle\Daemon;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Command\DaemonCommand;
use SoureCode\Bundle\Daemon\Command\DaemonStartCommand;
use SoureCode\Bundle\Daemon\Command\DaemonStopCommand;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
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
                ->scalarNode('pid_directory')
                    ->defaultValue('%kernel.project_dir%/var/run')
                    ->info('The directory where the pid, id and exit files are stored.')
                ->end()
                ->scalarNode('tmp_directory')
                    ->defaultValue('%kernel.project_dir%/var/tmp')
                    ->info('The directory where the tmp files are stored.')
                ->end()
                ->scalarNode('check_delay')
                    ->defaultValue(10 * 1000) // 10ms
                    ->info('Time in microseconds between checks.')
                    ->validate()
                        ->ifTrue(fn($value) => !is_int($value))
                        ->thenInvalid('The check delay must be an integer.')
                        ->ifTrue(fn($value) => $value < 1)
                        ->thenInvalid('The check delay must be greater than 0.')
                        ->ifTrue(fn($value) => $value > 9 * 1000 * 1000) // 10s
                        ->thenInvalid('Wtf are you doing? Waiting more than 9 seconds for a process to start? Really?')
                    ->end()
                ->end()
                ->scalarNode('check_timeout')
                    ->defaultValue(5)
                    ->info('Time in seconds after which the process is considered not started.')
                    ->validate()
                        ->ifTrue(fn($value) => !is_int($value))
                        ->thenInvalid('The check delay must be an integer.')
                        ->ifTrue(fn($value) => $value < 1)
                        ->thenInvalid('The check delay must be greater than 0.')
                        ->ifTrue(fn($value) => $value > 10)
                        ->thenInvalid('Wtf are you doing? Waiting more than 10 seconds for a process to start? Really?')
                    ->end()
            ->end();
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('soure_code_daemon.pid_directory', $config['pid_directory'])
            ->set('soure_code_daemon.tmp_directory', $config['tmp_directory'])
            ->set('soure_code_daemon.check_delay', $config['check_delay'])
            ->set('soure_code_daemon.check_timeout', $config['check_timeout'])
        ;

        $services = $container->services();

        $services->set('soure_code_daemon.daemon_manager', DaemonManager::class)
            ->args([
                service(LoggerInterface::class),
                service(Filesystem::class),
                param('kernel.project_dir'),
                param('soure_code_daemon.pid_directory'),
                param('soure_code_daemon.tmp_directory'),
                param('soure_code_daemon.check_delay'),
                param('soure_code_daemon.check_timeout'),
            ]);

        $services->alias(DaemonManager::class, 'soure_code_daemon.daemon_manager')
            ->public();

        $services->set('soure_code_daemon.command.daemon', DaemonCommand::class)
            ->args([
                service(LoggerInterface::class),
                service(ClockInterface::class),
                param('kernel.project_dir'),
                param('soure_code_daemon.pid_directory'),
            ])
            ->public()
            ->tag('console.command', ['command' => 'daemon'])
            ->tag('monolog.logger', ['channel' => 'daemon']);

        $services->set('soure_code_daemon.command.daemon.start', DaemonStartCommand::class)
            ->args([
                service('soure_code_daemon.daemon_manager'),
            ])
            ->public()
            ->tag('console.command', ['command' => 'daemon:start']);

        $services->set('soure_code_daemon.command.daemon.stop', DaemonStopCommand::class)
            ->args([
                service('soure_code_daemon.daemon_manager'),
            ])
            ->public()
            ->tag('console.command', ['command' => 'daemon:stop']);

    }
}