#!/usr/bin/env bash
# Runs only on a disposable Linux test host with loop devices and device-mapper.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo 'Run the disposable volume integration as root.' >&2; exit 1; }
assets=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
root=$(mktemp -d /tmp/nntmux-volume-test.XXXXXXXX)
loop=''
aliases=()
cleanup() {
    local status=$? mapper
    trap - EXIT
    if mountpoint -q "$root/data"; then umount "$root/data"; fi
    for mapper in nntmux-data nntmux-initialize; do
        if [[ -n "$loop" ]] && cryptsetup status "$mapper" | grep -Fq "$loop"; then cryptsetup close "$mapper"; fi
    done
    for alias in "${aliases[@]}"; do
        if [[ "$(readlink "$alias")" == "$loop" ]]; then rm "$alias"; fi
    done
    if [[ -n "$loop" ]]; then losetup -d "$loop"; fi
    rm -rf "$root"
    exit "$status"
}
trap 'cleanup' EXIT
for mapper in nntmux-data nntmux-initialize; do
    if cryptsetup status "$mapper" >/dev/null 2>&1; then echo 'Existing application mapper; refusing this test host.' >&2; exit 1; fi
done
export REAL_WIPEFS
REAL_WIPEFS=$(command -v wipefs)
mkdir -p "$root/bin" "$root/data" /dev/disk/by-id
truncate -s 128M "$root/disk"
loop=$(losetup --find --show "$root/disk")
for alias in /dev/disk/by-id/scsi-0HC_Volume_999999999 /dev/disk/by-id/virtio-aaaaaaaa-bbbb-cccc-d; do
    [[ ! -e "$alias" && ! -L "$alias" ]] || { echo 'Existing test device alias; refusing to replace it.' >&2; exit 1; }
    ln -s "$loop" "$alias"
    aliases+=("$alias")
done
cat > "$root/bin/curl" <<'CURL'
#!/bin/sh
cat "$VOLUME_SECRET"
CURL
cat > "$root/bin/wipefs" <<'WIPEFS'
#!/bin/sh
if [ "${INSPECTION_FAIL:-0}" = 1 ]; then exit 1; fi
exec "$REAL_WIPEFS" "$@"
WIPEFS
chmod 0755 "$root/bin/curl" "$root/bin/wipefs"
export PATH="$root/bin:$PATH" HOST_CONFIG="$root/host.json" DATA_ROOT="$root/data" VAULT_TOKEN_FILE="$root/token" VOLUME_SECRET="$root/secret.json" RUNTIME_ROOT="$root"
printf '{"provider":"hetzner","data_volume_id":"999999999","vault_address":"https://vault.example.com","vault_secret_path":"secret/data/test"}\n' > "$HOST_CONFIG"
printf '{"data":{"data":{"volume_key":"%s"}}}\n' "$(printf '%032d' 0 | base64 -w0)" > "$VOLUME_SECRET"
if "$assets/volume.sh" mount; then echo 'Blank volume was mounted.' >&2; exit 1; fi
[[ -z "$(wipefs --no-act --noheadings --output TYPE "$loop")" ]]
if "$assets/volume.sh" initialize; then echo 'Initialization succeeded without a Vault token.' >&2; exit 1; fi
[[ -z "$(wipefs --no-act --noheadings --output TYPE "$loop")" ]]
printf 'hvs.test-token\n' > "$VAULT_TOKEN_FILE"
chmod 0600 "$VAULT_TOKEN_FILE"
if INSPECTION_FAIL=1 "$assets/volume.sh" initialize; then echo 'Disk inspection failure allowed formatting.' >&2; exit 1; fi
[[ -z "$(wipefs --no-act --noheadings --output TYPE "$loop")" ]]
"$assets/volume.sh" initialize
uuid=$(cryptsetup luksUUID "$loop")
if "$assets/volume.sh" initialize; then echo 'Existing filesystem was reformatted.' >&2; exit 1; fi
[[ "$(cryptsetup luksUUID "$loop")" == "$uuid" ]]
"$assets/volume.sh" mount
"$assets/volume.sh" verify
printf 'persistent-data' > "$DATA_ROOT/payload"
"$assets/volume.sh" unmount
# The OVH virtio alias is deliberately truncated to the first 20 UUID characters.
jq '.provider="ovh" | .data_volume_id="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"' "$HOST_CONFIG" > "$root/host.new"
mv "$root/host.new" "$HOST_CONFIG"
"$assets/volume.sh" mount
[[ "$(cat "$DATA_ROOT/payload")" == persistent-data ]]
"$assets/volume.sh" verify-key
printf '{"data":{"data":{"volume_key":"%s"}}}\n' "$(printf '%032d' 1 | base64 -w0)" > "$VOLUME_SECRET"
if "$assets/volume.sh" verify-key; then echo 'Changed volume key was accepted.' >&2; exit 1; fi
"$assets/volume.sh" unmount
if "$assets/volume.sh" mount; then echo 'Volume unlocked with the wrong key.' >&2; exit 1; fi
[[ "$(cryptsetup luksUUID "$loop")" == "$uuid" ]]
echo 'Blank-disk guards, Vault failures, LUKS preservation, provider device discovery, remount, and key validation passed.'
