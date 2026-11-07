#!/bin/sh
cd "$(dirname "$0")/.." || exit 1
docker compose run --rm php "$@"
code=$?
if [ "${UFW_DOCKER_KEEP:-}" != "1" ]; then
  docker compose down
fi
exit "$code"
