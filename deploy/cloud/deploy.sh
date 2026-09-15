#!/usr/bin/env bash
set -euo pipefail
umask 077
ASSET_ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
HOST_CONFIG=${HOST_CONFIG:-/etc/nntmux/host.json}
STATE_ROOT=${STATE_ROOT:-$(jq -er '.state_root // "/etc/nntmux"' "$HOST_CONFIG")}
DATA_ROOT=${DATA_ROOT:-/srv/nntmux}
LOCK_FILE=${LOCK_FILE:-/run/lock/nntmux-deploy.lock}
export AWS_PAGER=''
provider=$(jq -er '.provider // "aws"' "$HOST_CONFIG")
case "$provider" in aws|hetzner|ovh) ;; *) echo 'Unsupported cloud provider.' >&2; exit 1 ;; esac
fail() { echo "$*" >&2; exit 1; }
operation=${1:-}
case "$operation" in initialize|release|rollback|backup|restore|start|stop) ;; *) fail 'Usage: deploy.sh initialize|release|rollback DIGEST | backup | restore SNAPSHOT [RECOVERY_VOLUME] [--activate] | start | stop' ;; esac
exec 9>"$LOCK_FILE"
flock -n 9 || fail 'Another deployment operation is running.'
region=$(jq -er '.region' "$HOST_CONFIG")
deployment=$(jq -er '.deployment' "$HOST_CONFIG")
hostname=$(jq -er '.hostname' "$HOST_CONFIG")
repository=$(jq -er '.repository' "$HOST_CONFIG")
volume_id=$(jq -er '.data_volume_id' "$HOST_CONFIG")
secret_arn=$(jq -r '.secret_arn // empty' "$HOST_CONFIG")
export region deployment volume_id secret_arn
# shellcheck source=/dev/null
source "$ASSET_ROOT/providers/$provider.sh"
require_mount() {
    mountpoint -q "$DATA_ROOT" || fail 'Persistent storage is not mounted.'
    "$ASSET_ROOT/volume.sh" verify
}
require_mount
install -d -m 0700 "$STATE_ROOT"
export DOCKER_CONFIG="$STATE_ROOT/docker"
ACTIVE_ENV="$STATE_ROOT/current.env"
compose() { docker compose --project-name nntmux --env-file "$ACTIVE_ENV" -f "$ASSET_ROOT/compose.yaml" "$@"; }
artisan() { compose run --rm --no-deps web php artisan "$@" --no-interaction; }
load_current() {
    [[ -f "$STATE_ROOT/current.env" ]] || fail 'No successful deployment is recorded.'
    ACTIVE_ENV="$STATE_ROOT/current.env"
    # This file contains only host-generated, shell-quoted paths and an image digest.
    # shellcheck source=/dev/null
    source "$ACTIVE_ENV"
    [[ -f "$CONFIG_DIRECTORY/application.env" ]] || fail 'Recorded application configuration is missing.'
}
write_env() {
    printf 'APP_IMAGE=%s\nAPPLICATION_HOSTNAME=%s\nDATA_ROOT=%s\nCONFIG_DIRECTORY=%s\n' "$APP_IMAGE" "$hostname" "$DATA_ROOT" "$CONFIG_DIRECTORY" > "$ACTIVE_ENV"
}
prepare_release() {
    local digest=${1:-}
    [[ "$digest" =~ ^sha256:[a-f0-9]{64}$ ]] || fail 'Supply an immutable sha256 image digest.'
    [[ "$STATE_ROOT" != *[[:space:]]* && "$DATA_ROOT" != *[[:space:]]* ]] || fail 'Deployment paths must not contain whitespace.'
    APP_IMAGE="$repository@$digest"
    CONFIG_DIRECTORY=$(mktemp -d "$STATE_ROOT/config.XXXXXXXX")
    if ! provider_fetch_configuration; then
        rm -rf "$CONFIG_DIRECTORY"
        fail 'Unable to retrieve application configuration.'
    fi
    provider_validate_configuration
    provider_registry_login
    docker pull "$APP_IMAGE"
    [[ -s "$CONFIG_DIRECTORY/application.env" ]] || fail 'Application configuration is empty.'
    docker run --rm --user 0:0 --entrypoint php -v "$CONFIG_DIRECTORY:/configuration" "$APP_IMAGE" /app/deploy/cloud/configure.php "$hostname"
    chown 33:33 "$CONFIG_DIRECTORY/application.env"
    chmod 0600 "$CONFIG_DIRECTORY/application.env"
    if [[ -f "$STATE_ROOT/current.env" ]]; then
        local previous_directory
        previous_directory=$(sed -n 's/^CONFIG_DIRECTORY=//p' "$STATE_ROOT/current.env")
        cmp -s "$previous_directory/identity.json" "$CONFIG_DIRECTORY/identity.json" || fail 'APP_KEY and database identity or passwords must remain unchanged during releases.'
    fi
    ACTIVE_ENV="$CONFIG_DIRECTORY/release.env"
    write_env
    compose config --quiet
}
record_release() {
    if [[ -f "$STATE_ROOT/current.env" ]]; then cp "$STATE_ROOT/current.env" "$STATE_ROOT/previous.env"; fi
    cp "$ACTIVE_ENV" "$STATE_ROOT/current.env.new"
    mv "$STATE_ROOT/current.env.new" "$STATE_ROOT/current.env"
    ACTIVE_ENV="$STATE_ROOT/current.env"
}
verify_services() {
    compose up -d --wait --wait-timeout 300
    compose run --rm --no-deps web php /app/deploy/cloud/health.php
    compose exec -T horizon php artisan horizon:status --no-interaction
    compose exec -T scheduler php /app/deploy/cloud/process-health.php schedule:work
    compose exec -T indexer php artisan tmux:health-check --session=nntmux --no-interaction
    curl --fail --silent --show-error --retry 12 --retry-delay 5 --retry-all-errors "https://$hostname/up" >/dev/null
}
stop_writers() {
    artisan down --retry=60
    local running
    running=$(compose ps --status running --services)
    if grep -qx indexer <<< "$running"; then
        compose exec -T indexer php artisan tmux:stop --session=nntmux --no-interaction
    fi
    compose stop indexer horizon scheduler
}
finish_restore() {
    # Install the failure handler before starting any restored services.
    # shellcheck disable=SC2317
    restore_failure() {
        local status=$?
        if [[ $status -ne 0 ]]; then
            compose stop indexer horizon scheduler web caddy || true
            echo 'Restore verification failed; application writers remain stopped.' >&2
        fi
        exit "$status"
    }
    trap 'restore_failure' EXIT
    systemctl start docker.service
    provider_registry_login
    docker pull "$APP_IMAGE"
    compose up -d --wait --wait-timeout 300 mariadb redis manticore
    artisan down --retry=60
    verify_services
    artisan up
    trap - EXIT
}
# A subshell keeps its recovery trap separate from the release transaction.
snapshot() (
    local purpose=$1
    shift
    local restart_services=("$@")
    if [[ ${#restart_services[@]} -eq 0 ]]; then
        mapfile -t restart_services < <(compose ps --status running --services)
    fi
    install -d -m 0700 "$DATA_ROOT/recovery/config"
    cp "$CONFIG_DIRECTORY/"{application.env,database.env,identity.json} "$DATA_ROOT/recovery/config/"
    if [[ -f "$CONFIG_DIRECTORY/credentials.json" ]]; then cp "$CONFIG_DIRECTORY/credentials.json" "$DATA_ROOT/recovery/config/"; fi
    cp "$ACTIVE_ENV" "$DATA_ROOT/recovery/release.env"
    # Install the trap before stopping anything so partial stop failures also recover.
    # The EXIT trap invokes this callback after success or failure.
    # shellcheck disable=SC2317
    restart_snapshot_services() {
        local snapshot_status=$?
        trap - EXIT
        systemctl start docker.service || snapshot_status=1
        if [[ ${#restart_services[@]} -gt 0 ]]; then
            compose up -d --wait --wait-timeout 300 "${restart_services[@]}" || snapshot_status=1
        fi
        exit "$snapshot_status"
    }
    trap 'restart_snapshot_services' EXIT
    compose stop
    systemctl stop docker.service docker.socket containerd.service
    sync
    provider_backup "$purpose"
)
case "$operation" in
    initialize)
        [[ ! -e "$DATA_ROOT/install/install.lock" ]] || fail 'This installation is already initialized.'
        prepare_release "${2:-}"
        install -d -o 33 -g 33 -m 0770 "$DATA_ROOT/storage" "$DATA_ROOT/install"
        compose up -d --wait --wait-timeout 300 mariadb redis manticore
        artisan nntmux:deploy-init
        # Persist the release before starting services so a failed readiness check remains recoverable.
        record_release
        verify_services
        systemctl enable --now nntmux-backup.timer
        ;;
    release|rollback)
        load_current
        [[ -f "$DATA_ROOT/install/install.lock" ]] || fail 'Initialize the deployment explicitly before releasing.'
        if [[ "$operation" == rollback ]]; then
            [[ ${3:-} == --schema-compatible ]] || fail 'Rollback requires --schema-compatible; restore a backup if the schema is incompatible.'
        fi
        old_env="$ACTIVE_ENV"
        old_config="$CONFIG_DIRECTORY"
        prepare_release "${2:-}"
        candidate_env="$ACTIVE_ENV"
        candidate_config="$CONFIG_DIRECTORY"
        ACTIVE_ENV="$old_env"
        CONFIG_DIRECTORY="$old_config"
        stop_writers
        snapshot release mariadb redis manticore web caddy
        ACTIVE_ENV="$candidate_env"
        CONFIG_DIRECTORY="$candidate_config"
        if [[ "$operation" == release ]]; then
            if ! artisan migrate --force; then
                fail 'Migration failed. Maintenance remains enabled and indexing is stopped. Recover explicitly; no schema rollback was attempted.'
            fi
        fi
        # Once migrations succeed, reboot must use the new image rather than the previous schema contract.
        record_release
        # Invoked by EXIT when readiness fails, preserving the original exit status.
        # shellcheck disable=SC2317
        release_failure() {
            local failure_status=$?
            if [[ $failure_status -ne 0 ]]; then
                compose stop indexer horizon scheduler || true
                echo "Release verification failed; maintenance remains enabled." >&2
            fi
            exit "$failure_status"
        }
        trap 'release_failure' EXIT
        verify_services
        artisan up
        trap - EXIT
        ;;
    backup)
        load_current
        [[ -f "$DATA_ROOT/install/install.lock" ]] || fail 'Cannot back up an uninitialized deployment.'
        # Full service stop prevents web, queues, scheduler, and indexing writes.
        snapshot daily
        provider_after_backup
        ;;
    start)
        load_current
        [[ -f "$DATA_ROOT/install/install.lock" ]] || fail 'Initialize the deployment explicitly before starting services.'
        verify_services
        ;;
    stop)
        load_current
        compose stop
        ;;
    restore)
        load_current
        provider_restore "$@"
        ;;
esac
