#!/usr/bin/env bash
# Exercise actual Restic encryption, retention, and subfolder restore using a local test repository.
set -euo pipefail
umask 077
ASSET_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
root=$(mktemp -d /tmp/nntmux-restic-test.XXXXXXXX)
trap 'rm -rf "$root"' EXIT
DATA_ROOT="$root/data"
CONFIG_DIRECTORY="$root/config"
export deployment=nntmux-hetzner-test
mkdir -p "$DATA_ROOT/storage" "$DATA_ROOT/docker" "$CONFIG_DIRECTORY" "$root/restored"
printf 'ephemeral-layer' > "$DATA_ROOT/docker/layer"
jq -n --arg repository "$root/repository" '{restic_repository:$repository,restic_password:"test-only-encryption-password",aws_access_key_id:"test-user",aws_secret_access_key:"test-key"}' > "$CONFIG_DIRECTORY/credentials.json"
# The local repository deliberately bypasses only the HTTPS S3 preflight interface.
# shellcheck source=/dev/null
source "$ASSET_ROOT/providers/portable.sh"
for ((number=1; number<=9; number++)); do
    printf 'recovery-point-%s' "$number" > "$DATA_ROOT/storage/payload"
    provider_backup daily >/dev/null
done
restic_cli snapshots --json > "$root/snapshots.json"
[[ "$(jq 'length' "$root/snapshots.json")" == 7 ]]
snapshot=$(jq -r 'sort_by(.time) | last | .id' "$root/snapshots.json")
restic_cli restore "$snapshot:$DATA_ROOT" --target "$root/restored" --verify >/dev/null
[[ "$(cat "$root/restored/storage/payload")" == recovery-point-9 ]]
[[ ! -e "$root/restored/docker" ]]
printf 'release-recovery' > "$DATA_ROOT/storage/payload"
provider_backup release >/dev/null
restic_cli snapshots --json | jq -e 'length == 8 and ([.[] | select(.tags | index("daily"))] | length == 7)' >/dev/null
cp "$CONFIG_DIRECTORY/credentials.json" "$root/credentials.original"
jq '.restic_password="wrong-password"' "$root/credentials.original" > "$CONFIG_DIRECTORY/credentials.json"
for purpose in daily release; do
    if (provider_backup "$purpose") >/dev/null 2>&1; then echo 'Existing repository accepted the wrong password.' >&2; exit 1; fi
done
cp "$root/credentials.original" "$CONFIG_DIRECTORY/credentials.json"
restic_cli snapshots --json | jq -e 'length == 8' >/dev/null
provider_after_backup >/dev/null
restic_cli check >/dev/null
echo 'Restic seven-point retention, release-backup retention, verified subfolder restore, password guards, and pruning passed.'
