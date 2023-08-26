
# Command: daemon

## Usage

```shell
Description:
  This command is the daemon supervisor

Usage:
  daemon [options] [--] <process>...

Arguments:
  process                               The process to run

Options:
  -i, --id=ID                           The daemon id
  -r, --auto-restart|--no-auto-restart  Auto restart daemon on exit
  -h, --help                            Display help for the given command. When no command is given display help for the list command
  -q, --quiet                           Do not output any message
  -V, --version                         Display this application version
      --ansi|--no-ansi                  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction                  Do not ask any interactive question
  -e, --env=ENV                         The Environment name. [default: "dev"]
      --no-debug                        Switch off debug mode.
  -v|vv|vvv, --verbose                  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```


