
# DaemonBundle

## Requirements

- PHP 8.2 or higher
- Symfony 6.3 or higher

## Commands

- [`daemon:start`](./daemon-start.md) - Starts a daemon.
- [`daemon:stop`](./daemon-stop.md) - Stop one or all daemons.

## Important

If you are using the systemd, you must enable lingering.

> enable-linger [USER…], disable-linger [USER…]
> 
> Enable/disable user lingering for one or more users. If enabled for a specific user, a user manager is spawned for the user at boot and kept around after logouts. This allows users who are not logged in to run long-running services. Takes one or more user names or numeric UIDs as argument. If no argument is specified, enables/disables lingering for the user of the session of the caller.

```shell
loginctl enable-linger <username>
```

## Examples

```shell
# Start npm watch as a daemon
$ symfony console daemon:start npm

# Stop a daemon
$ symfony console daemon:stop npm

# Stop all daemons
$ symfony console daemon:stop --all
```

An php api is also available:

```php
use SoureCode\Bundle\Daemon\Manager\DaemonManager;

$daemonManager = $container->get(DaemonManager::class);

// start npm watch as a daemon
$daemonManager->start('npm');

// stop npm watch daemon
$daemonManager->stop('npm');

// stop all daemons
$daemonManager->stopAll();

// check if daemon is running
$daemonManager->isRunning('npm');
```

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
composer require sourecode/daemon-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require sourecode/daemon-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    \SoureCode\Bundle\Daemon\SoureCodeDaemonBundle::class => ['all' => true],
];
```