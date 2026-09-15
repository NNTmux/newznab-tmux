#!/usr/bin/env bash
# Variables and assignments form the interface with the sourcing transaction.
# shellcheck disable=SC2154,SC2034
# Hetzner and OVH share registry, Vault, and encrypted file-backup interfaces.
# shellcheck source=/dev/null
source "$ASSET_ROOT/providers/vault.sh"
provider_fetch_configuration() {
    local response
    response=$(vault_fetch) || return 1
    jq -er '.data.data.application_env | select(type == "string" and length > 0)' <<< "$response" > "$CONFIG_DIRECTORY/application.env" || return 1
    jq '.data.data | del(.application_env, .volume_key)' <<< "$response" > "$CONFIG_DIRECTORY/credentials.json"
    chmod 0600 "$CONFIG_DIRECTORY/credentials.json"
}
provider_validate_configuration() {
    "$ASSET_ROOT/volume.sh" verify-key
    jq -e '.restic_repository | type == "string" and startswith("s3:https://")' "$CONFIG_DIRECTORY/credentials.json" >/dev/null || fail 'Supply a Restic S3 repository using HTTPS.'
    jq -e 'all([.restic_password, .aws_access_key_id, .aws_secret_access_key][]; type == "string" and length > 0)' "$CONFIG_DIRECTORY/credentials.json" >/dev/null || fail 'Missing backup credentials.'
    if jq -e '.registry_username != null or .registry_password != null' "$CONFIG_DIRECTORY/credentials.json" >/dev/null; then
        jq -e 'all([.registry_username, .registry_password][]; type == "string" and length > 0)' "$CONFIG_DIRECTORY/credentials.json" >/dev/null || fail 'Supply both registry credentials or neither.'
    fi
}
provider_registry_login() {
    local username
    username=$(jq -r '.registry_username // empty' "$CONFIG_DIRECTORY/credentials.json")
    if [[ -n "$username" ]]; then
        jq -er '.registry_password' "$CONFIG_DIRECTORY/credentials.json" | docker login --username "$username" --password-stdin "${repository%%/*}" >/dev/null
    fi
}
restic_cli() (
    # Process-local credentials are never sourced as executable shell text.
    export RESTIC_REPOSITORY RESTIC_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION
    RESTIC_REPOSITORY=$(jq -er '.restic_repository' "$CONFIG_DIRECTORY/credentials.json")
    RESTIC_PASSWORD=$(jq -er '.restic_password' "$CONFIG_DIRECTORY/credentials.json")
    AWS_ACCESS_KEY_ID=$(jq -er '.aws_access_key_id' "$CONFIG_DIRECTORY/credentials.json")
    AWS_SECRET_ACCESS_KEY=$(jq -er '.aws_secret_access_key' "$CONFIG_DIRECTORY/credentials.json")
    AWS_DEFAULT_REGION=$(jq -r '.aws_default_region // "us-east-1"' "$CONFIG_DIRECTORY/credentials.json")
    restic "$@"
)
provider_backup() {
    local purpose=$1
    # Restic init refuses an existing repository; no repository is overwritten.
    restic_cli cat config >/dev/null || restic_cli init || return 1
    restic_cli backup "$DATA_ROOT" --one-file-system --host "$deployment" --tag "$deployment" --tag "$purpose" --exclude "$DATA_ROOT/lost+found" --exclude "$DATA_ROOT/docker" || return 1
    if [[ "$purpose" == daily ]]; then
        restic_cli forget --tag "$deployment,daily" --host "$deployment" --group-by host,tags --keep-last 7 || return 1
        # Garbage collection is separate: it must not extend stopped-service downtime.
    fi
}
provider_after_backup() { restic_cli prune; }
provider_restore() {
    local snapshot_id=${2:-} target_id=${3:-} activate=${4:-}
    local stage stage_config recovered_image recovered_config state_relative existing_stage
    [[ "$snapshot_id" =~ ^[a-f0-9]{64}$ ]] || fail 'Supply a full Restic snapshot ID.'
    [[ "$target_id" != "$volume_id" && "$target_id" =~ ^[a-f0-9-]+$ ]] || fail 'Supply a separate, initialized recovery volume ID.'
    [[ "$activate" == '' || "$activate" == --activate ]] || fail 'Use --activate to switch to the recovered volume.'
    restic_cli snapshots --json "$snapshot_id" | jq -e --arg deployment "$deployment" 'length == 1 and (.[0].hostname == $deployment) and (.[0].tags | index($deployment) != null)' >/dev/null || fail 'Snapshot does not belong to this deployment.'
    existing_stage=$(findmnt -rn -S /dev/mapper/nntmux-restore -o TARGET || true)
    if [[ -n "$existing_stage" ]]; then
        [[ "$existing_stage" == "${RESTORE_ROOT:-/mnt}/nntmux-restore."* && "$existing_stage" != *[[:space:]]* ]] || fail 'Unexpected staging mount; inspect it before restoring.'
        stage="$existing_stage"
    else
        stage=$(mktemp -d "${RESTORE_ROOT:-/mnt}/nntmux-restore.XXXXXXXX")
    fi
    stage_config=$(mktemp "${RUNTIME_ROOT:-/run}/nntmux-restore-host.XXXXXXXX")
    jq --arg id "$target_id" '.data_volume_id = $id' "$HOST_CONFIG" > "$stage_config"
    HOST_CONFIG="$stage_config" DATA_ROOT="$stage" MAPPER_NAME=nntmux-restore "$ASSET_ROOT/volume.sh" mount
    if [[ -n "$(find "$stage" -mindepth 1 -maxdepth 1 ! -name lost+found -print -quit)" ]]; then
        [[ "$activate" == --activate && -f "$stage/.nntmux-restore.json" ]] || fail "Recovery volume is not empty; inspect $stage."
        jq -e --arg snapshot "$snapshot_id" --arg target "$target_id" '.snapshot_id == $snapshot and .volume_id == $target' "$stage/.nntmux-restore.json" >/dev/null || fail 'Staged recovery belongs to another snapshot or volume.'
    else
        restic_cli restore "$snapshot_id:$DATA_ROOT" --target "$stage" --verify
    fi
    [[ -f "$stage/install/install.lock" && -f "$stage/recovery/config/identity.json" && -f "$stage/recovery/release.env" ]] || fail "Recovery metadata is incomplete; inspect $stage."
    cmp -s "$CONFIG_DIRECTORY/identity.json" "$stage/recovery/config/identity.json" || fail "Recovery key or database identity differs; inspect $stage."
    jq -n --arg snapshot "$snapshot_id" --arg target "$target_id" '{snapshot_id:$snapshot,volume_id:$target}' > "$stage/.nntmux-restore.json"
    if [[ "$activate" != --activate ]]; then
        echo "Verified recovery volume mounted at $stage; staging host configuration: $stage_config. Activate with restore $snapshot_id $target_id --activate, or unmount with HOST_CONFIG=$stage_config DATA_ROOT=$stage MAPPER_NAME=nntmux-restore volume.sh unmount."
        return
    fi
    recovered_image=$(sed -n 's/^APP_IMAGE=//p' "$stage/recovery/release.env")
    [[ "${recovered_image%@*}" == "$repository" && "${recovered_image##*@}" =~ ^sha256:[a-f0-9]{64}$ ]] || fail 'Recovery image does not match the configured registry.'
    provider_registry_login
    docker pull "$recovered_image"
    stop_writers
    snapshot before-restore mariadb redis manticore web caddy
    compose stop
    systemctl stop docker.service docker.socket containerd.service
    # Restore credentials are retained on the recovered encrypted data filesystem.
    state_relative=${STATE_ROOT#"$DATA_ROOT/"}
    [[ "$state_relative" != "$STATE_ROOT" ]] || fail 'Portable deployment state must reside on the data volume.'
    recovered_config=$(mktemp -d "$stage/$state_relative/config.recovery.XXXXXXXX")
    cp "$stage/recovery/config/"{application.env,database.env,identity.json,credentials.json} "$recovered_config/"
    recovered_config="$DATA_ROOT/${recovered_config#"$stage/"}"
    HOST_CONFIG="$stage_config" DATA_ROOT="$stage" MAPPER_NAME=nntmux-restore "$ASSET_ROOT/volume.sh" unmount
    "$ASSET_ROOT/volume.sh" unmount
    cp "$stage_config" "$HOST_CONFIG.new"
    mv "$HOST_CONFIG.new" "$HOST_CONFIG"
    volume_id="$target_id"
    "$ASSET_ROOT/volume.sh" mount
    CONFIG_DIRECTORY="$recovered_config"
    chown 33:33 "$CONFIG_DIRECTORY/application.env"
    chmod 0600 "$CONFIG_DIRECTORY/application.env"
    APP_IMAGE="$recovered_image"
    ACTIVE_ENV="$CONFIG_DIRECTORY/release.env"
    write_env
    record_release
    finish_restore
    rm -f "$stage_config"
    echo 'Recovery activated; retain the original volume and select use_recovery_volume in Terraform before the next apply.'
}
