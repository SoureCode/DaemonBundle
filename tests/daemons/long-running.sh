#!/usr/bin/env bash

# This is an example process which runs for 2 seconds and then exits.

for i in {1..60}; do
  echo "iteration: $i"
  sleep 1
done
exit 0
