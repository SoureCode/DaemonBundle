#!/usr/bin/env bash

# This is an example process which runs for 2 seconds and then exits.

function exiting() {
  echo "exiting"
  exit 0
}

trap exiting SIGTERM SIGINT

for i in {1..60}; do
  echo "iteration: $i"
  sleep 1
done

echo "done"
exit 0