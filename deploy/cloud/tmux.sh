#!/bin/sh
set -eu
cd /app
# Docker signals invoke this callback; it is not reached by the main loop.
# shellcheck disable=SC2317
stop() {
    php artisan tmux:stop --session=nntmux --no-interaction || true
    exit 0
}
trap 'stop' TERM INT
php artisan tmux:start --session=nntmux --no-interaction
while tmux has-session -t nntmux 2>/dev/null; do
    sleep 10 &
    wait "$!" || true
done
echo "Tmux session exited unexpectedly." >&2
exit 1
