#!/bin/bash
set -euo pipefail
cd /srv/nntmux/current
# Invoked by the signal trap when systemd stops the service.
# shellcheck disable=SC2317
stop() {
    php8.5 artisan tmux:stop --session=nntmux --force --no-interaction
    exit 0
}
trap stop TERM INT
php8.5 artisan tmux:start --session=nntmux --no-interaction
while php8.5 artisan tmux:health-check --session=nntmux --require-session --quiet; do
    sleep 10 &
    wait "$!" || true
done
echo 'NNTmux tmux session exited unexpectedly.' >&2
exit 1
