#!/usr/bin/env bash

function _exiting() {
  echo "bye"
  exit 0
}

trap _exiting SIGTERM SIGINT EXIT

for i in {1..60}; do
  echo "yeet: $i"
  sleep 1
done

echo "wuhuu"
exit 0