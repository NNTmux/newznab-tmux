#!/bin/sh
# Supervises the NNTmux tmux engine inside the indexer container.
# The health check repairs a dead monitor pane or a missing session here (where tmux lives);
# after repeated failures the container exits so Docker's restart policy recreates it.
set -eu
cd /app

SESSION="${TMUX_SESSION_NAME:-nntmux}"
INTERVAL="${INDEXER_HEALTH_INTERVAL:-30}"
MAX_FAILURES="${INDEXER_MAX_FAILURES:-10}"

# Docker signals invoke this callback; it is not reached by the main loop.
# shellcheck disable=SC2317
stop() {
    php artisan tmux:stop --session="$SESSION" --force --no-interaction || true
    exit 0
}
trap 'stop' TERM INT

mkdir -p /var/tmp/nntmux/unrar /var/tmp/nntmux/unzip

php artisan tmux:start --session="$SESSION" --no-interaction

failures=0
while :; do
    sleep "$INTERVAL" &
    wait "$!" || true
    if php artisan tmux:health-check --session="$SESSION" --auto-restart --quiet; then
        failures=0
    else
        failures=$((failures + 1))
        echo "tmux health check failed (${failures}/${MAX_FAILURES})" >&2
        if [ "$failures" -ge "$MAX_FAILURES" ]; then
            exit 1
        fi
    fi
done
