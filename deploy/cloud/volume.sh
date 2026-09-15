#!/usr/bin/env bash
set -euo pipefail
HOST_CONFIG=${HOST_CONFIG:-/etc/nntmux/host.json}
DATA_ROOT=${DATA_ROOT:-/srv/nntmux}
ASSET_ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
provider=$(jq -er '.provider // "aws"' "$HOST_CONFIG")
MAPPER_NAME=${MAPPER_NAME:-nntmux-data}
case "$provider" in aws|hetzner|ovh) ;; *) echo "Unsupported cloud provider." >&2; exit 1 ;; esac
volume_id=$(jq -er '.data_volume_id' "$HOST_CONFIG")
find_device() {
    if [[ "$provider" == hetzner ]]; then
        [[ "$1" =~ ^[0-9]+$ ]] || return 1
        local path="/dev/disk/by-id/scsi-0HC_Volume_$1"
        [[ -b "$path" ]] || return 1
        printf '%s\n' "$path"
        return
    elif [[ "$provider" == ovh ]]; then
        [[ "$1" =~ ^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$ ]] || return 1
        local path="/dev/disk/by-id/virtio-${1:0:20}"
        [[ -b "$path" ]] || return 1
        printf '%s\n' "$path"
        return
    fi
    local id=${1//-/} device serial
    for device in /dev/nvme*n1 /dev/xvd[f-g]; do
        [[ -b "$device" ]] || continue
        serial=$(lsblk -dn -o SERIAL "$device" | tr -d '[:space:]-')
        if [[ "$serial" == "$id" ]]; then
            printf '%s\n' "$device"
            return 0
        fi
    done
    echo "The configured EBS volume is not attached: $1" >&2
    return 1
}
wait_device() {
    local attempt
    for ((attempt=0; attempt<60; attempt++)); do
        if device=$(find_device "$volume_id" 2>/dev/null); then return 0; fi
        sleep 2
    done
    find_device "$volume_id"
}
verify() {
    mountpoint -q "$DATA_ROOT" || { echo "Persistent storage is not mounted." >&2; exit 1; }
    local expected actual
    expected=$(find_device "$volume_id")
    if [[ "$provider" != aws ]]; then
        cryptsetup status "$MAPPER_NAME" | awk '$1 == "device:" {print $2}' | xargs readlink -f | cmp -s - <(readlink -f "$expected") || { echo "Unexpected encrypted volume." >&2; exit 1; }
        expected="/dev/mapper/$MAPPER_NAME"
    fi
    actual=$(findmnt -rn -o SOURCE --target "$DATA_ROOT")
    [[ "$(readlink -f "$actual")" == "$(readlink -f "$expected")" ]] || { echo "Unexpected data volume mounted." >&2; exit 1; }
}
key_file=''
cleanup_key() { if [[ -n "$key_file" ]]; then rm -f "$key_file"; fi; }
trap 'cleanup_key' EXIT
prepare_key() {
    # shellcheck source=/dev/null
    source "$ASSET_ROOT/providers/vault.sh"
    key_file=$(mktemp /run/nntmux-volume-key.XXXXXXXX)
    chmod 0600 "$key_file"
    vault_volume_key > "$key_file"
    [[ $(stat -c '%s' "$key_file") -ge 32 ]] || { echo 'Volume key must decode to at least 32 bytes.' >&2; exit 1; }
}
case ${1:-} in
    initialize)
        [[ $EUID -eq 0 ]] || exit 1
        exec 9>/run/lock/nntmux-deploy.lock
        flock -n 9 || { echo "Another deployment operation is running." >&2; exit 1; }
        MAPPER_NAME=nntmux-initialize
        volume_id=${2:-$volume_id}
        wait_device
        # Signatures, partitions, or mounts mean this disk is not blank.
        signatures=$(wipefs --no-act --noheadings --output TYPE "$device") || { echo "Unable to inspect disk signatures; refusing to format." >&2; exit 1; }
        [[ -z "$signatures" ]] || { echo "Refusing to format a disk with an existing signature." >&2; exit 1; }
        [[ "$(lsblk -nr -o NAME "$device" | wc -l)" -eq 1 ]] || { echo "Refusing to format a partitioned disk." >&2; exit 1; }
        [[ -z "$(lsblk -nr -o MOUNTPOINTS "$device" | tr -d '[:space:]')" ]] || exit 1
        if [[ "$provider" == aws ]]; then
            mkfs.ext4 -L nntmux-data "$device"
        else
            prepare_key
            cryptsetup luksFormat --batch-mode --type luks2 --key-file "$key_file" "$device"
            cryptsetup open --key-file "$key_file" "$device" "$MAPPER_NAME"
            mkfs.ext4 -L nntmux-data "/dev/mapper/$MAPPER_NAME"
            cryptsetup close "$MAPPER_NAME"
        fi
        ;;
    mount)
        wait_device
        if [[ "$provider" != aws ]]; then
            cryptsetup isLuks "$device" || { echo 'Expected an initialized LUKS volume.' >&2; exit 1; }
            prepare_key
            if cryptsetup status "$MAPPER_NAME" >/dev/null 2>&1; then
                cryptsetup open --test-passphrase --key-file "$key_file" "$device"
            else
                cryptsetup open --key-file "$key_file" "$device" "$MAPPER_NAME"
            fi
            device="/dev/mapper/$MAPPER_NAME"
        fi
        [[ "$(blkid -s TYPE -o value "$device")" == ext4 ]] || { echo "Expected ext4 storage; initialize a blank disk explicitly." >&2; exit 1; }
        mkdir -p "$DATA_ROOT"
        if ! mountpoint -q "$DATA_ROOT"; then mount -o defaults,nodev,nosuid "$device" "$DATA_ROOT"; fi
        verify
        ;;
    unmount)
        if mountpoint -q "$DATA_ROOT"; then verify; umount "$DATA_ROOT"; fi
        if [[ "$provider" != aws ]] && cryptsetup status "$MAPPER_NAME" >/dev/null 2>&1; then cryptsetup close "$MAPPER_NAME"; fi
        ;;
    verify-key)
        verify
        prepare_key
        device=$(find_device "$volume_id")
        cryptsetup open --test-passphrase --key-file "$key_file" "$device"
        ;;
    verify) verify ;;
    *) echo "Usage: volume.sh initialize|mount|unmount|verify" >&2; exit 2 ;;
esac
