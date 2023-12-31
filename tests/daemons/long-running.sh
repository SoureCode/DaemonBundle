#!/usr/bin/env bash

function _exiting() {
  echo "exiting"
  exit 0
}

trap _exiting SIGTERM SIGINT EXIT

for i in {1..60}; do
  echo "iteration: $i"
  sleep 1
done

echo "done"
exit 0