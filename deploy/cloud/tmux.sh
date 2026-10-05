#!/bin/sh
set -eu
cd /app
# Docker signals invoke this callback; it is not reached by the main loop.
# shellcheck disable=SC2317
stop() {
    php artisan tmux:stop --session=nntmux --force --no-interaction
    exit 0
}
trap 'stop' TERM INT
php artisan tmux:start --session=nntmux --no-interaction
while php artisan tmux:health-check --session=nntmux --require-session --quiet; do
    sleep 10 &
    wait "$!" || true
done
echo "Tmux session exited unexpectedly." >&2
exit 1
