<?php

namespace SoureCode\Bundle\Daemon;

use Psr\Clock\ClockInterface;
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
                ->end()
                ->scalarNode('log_check_delay')
                    ->defaultValue(100 * 1000) // 100ms
                    ->info('Time in microseconds to wait before checking logs and if it is still running.')
                    ->validate()
                        ->ifTrue(fn($value) => !is_int($value))
                        ->thenInvalid('The check delay must be an integer.')
                        ->ifTrue(fn($value) => $value < 1)
                        ->thenInvalid('The check delay must be greater than 0.')
                        ->ifTrue(fn($value) => $value > 5 * 1000 * 1000) // 5s
                        ->thenInvalid('Wtf are you doing? Waiting more than 5 seconds for a process to start? Really?')
                    ->end()
                ->end()
                ->arrayNode('daemons')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('command')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(fn($value) => !is_string($value))
                                    ->thenInvalid('Command must be a string.')
                                ->end()
                            ->end()
                            ->scalarNode('timeout')
                                ->validate()
                                    ->ifTrue(fn($value) => !is_int($value))
                                    ->thenInvalid('Timeout must be an integer.')
                                ->end()
                            ->end()
                            ->arrayNode('signals')
                                ->validate()
                                    ->ifTrue(fn($value) => !is_array($value))
                                    ->thenInvalid('Signals must be an array.')
                                    ->ifTrue(fn($value) => count($value) < 1)
                                    ->thenInvalid('Signals must contain at least one signal.')
                                    ->ifTrue(fn($value) => array_filter($value, fn($signal) => !is_int($signal)) > 0)
                                    ->thenInvalid('Signals must be an array of integers.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
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
            ->set('soure_code_daemon.log_check_delay', $config['log_check_delay'])
            ->set('soure_code_daemon.daemons', $config['daemons'])
        ;

        $services = $container->services();

        $services->set('soure_code_daemon.daemon_manager', DaemonManager::class)
            ->args([
                service('logger'),
                service(Filesystem::class),
                param('kernel.project_dir'),
                param('soure_code_daemon.pid_directory'),
                param('soure_code_daemon.tmp_directory'),
                param('soure_code_daemon.check_delay'),
                param('soure_code_daemon.check_timeout'),
                param('soure_code_daemon.log_check_delay'),
                param('soure_code_daemon.daemons'),
            ])
            ->tag('monolog.logger', ['channel' => 'daemon']);

        $services->alias(DaemonManager::class, 'soure_code_daemon.daemon_manager')
            ->public();

        $services->set('soure_code_daemon.command.daemon', DaemonCommand::class)
            ->args([
                service('logger'),
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