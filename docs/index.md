
# DaemonBundle

## Requirements

- PHP 8.2 or higher
- php-pcntl
- php-posix

## Commands

- [`daemon`](./daemon.md) - The daemon itself.
- [`daemon:start`](./daemon-start.md) - Starts a daemon.
- [`daemon:stop`](./daemon-stop.md) - Stop one or all daemons.

## Examples

```shell
# Start npm watch as a daemon
$ symfony console daemon:start --id npm "npm run watch"

# Start a worker as a daemon
$ symfony console daemon:start --id worker1 "symfony console messenger:consume async"
$ symfony console daemon:start --id worker2 "symfony console messenger:consume async_high"

# Stop a daemon
$ symfony console daemon:stop --id npm

# Stop all daemons
$ symfony console daemon:stop --all
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