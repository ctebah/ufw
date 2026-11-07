#!/bin/sh
cd "$(dirname "$0")/.." || exit 1
action="${1:-up}"
case "$action" in
  up) docker compose up -d mysql ;;
  down) docker compose down ;;
  status) docker compose ps ;;
  *) echo "usage: $0 up|down|status" >&2; exit 2 ;;
esac
