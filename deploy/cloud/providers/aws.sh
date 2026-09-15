#!/usr/bin/env bash
# Variables and assignments form the interface with the sourcing transaction.
# shellcheck disable=SC2154,SC2034
# AWS-specific cloud calls; sourced by the shared deployment transaction.
aws_cli() { aws --region "$region" "$@"; }
provider_registry_login() {
    aws_cli ecr get-login-password | docker login --username AWS --password-stdin "${repository%%/*}" >/dev/null
}
provider_fetch_configuration() {
    aws_cli secretsmanager get-secret-value --secret-id "$secret_arn" --query SecretString --output text > "$CONFIG_DIRECTORY/application.env"
}
provider_validate_configuration() { :; }
provider_after_backup() { :; }
instance_id() {
    local token
    token=$(curl -fsS -X PUT -H 'X-aws-ec2-metadata-token-ttl-seconds: 60' http://169.254.169.254/latest/api/token)
    curl -fsS -H "X-aws-ec2-metadata-token: $token" http://169.254.169.254/latest/meta-data/instance-id
}
prune_snapshots() {
    local obsolete snapshot
    obsolete=$(aws_cli ec2 describe-snapshots --owner-ids self --filters "Name=tag:Deployment,Values=$deployment" 'Name=tag:Purpose,Values=daily' 'Name=status,Values=completed' --output json | jq -r '.Snapshots | sort_by(.StartTime) | reverse | .[7:][] | .SnapshotId')
    for snapshot in $obsolete; do aws_cli ec2 delete-snapshot --snapshot-id "$snapshot"; done
}
provider_backup() {
    local purpose=$1 snapshot_id
    snapshot_id=$(aws_cli ec2 create-snapshot --volume-id "$volume_id" --description "$deployment $purpose $(date -u +%FT%TZ)" --tag-specifications "ResourceType=snapshot,Tags=[{Key=Deployment,Value=$deployment},{Key=Purpose,Value=$purpose},{Key=Name,Value=$deployment-backup}]" --query SnapshotId --output text)
    echo "Snapshot initiated: $snapshot_id" >&2
    aws_cli ec2 wait snapshot-completed --snapshot-ids "$snapshot_id"
    [[ "$purpose" != daily ]] || prune_snapshots
}
provider_restore() {
        load_current
        snapshot_id=${2:-}
        [[ "$snapshot_id" =~ ^snap-[a-f0-9]+$ ]] || fail 'Supply an EBS snapshot ID.'
        [[ ${3:-} == '' || ${3:-} == --activate ]] || fail 'Use --activate to explicitly switch to the restored volume.'
        details=$(aws_cli ec2 describe-snapshots --owner-ids self --snapshot-ids "$snapshot_id" --output json)
        jq -e --arg deployment "$deployment" '.Snapshots | length == 1 and (.[0].State == "completed") and (.[0].Encrypted == true) and any(.[0].Tags[]; .Key == "Deployment" and .Value == $deployment)' <<< "$details" >/dev/null || fail 'Snapshot is not a completed, encrypted backup of this deployment.'
        instance=$(instance_id)
        zone=$(aws_cli ec2 describe-instances --instance-ids "$instance" --query 'Reservations[0].Instances[0].Placement.AvailabilityZone' --output text)
        restored=$(aws_cli ec2 create-volume --snapshot-id "$snapshot_id" --availability-zone "$zone" --volume-type gp3 --encrypted --tag-specifications "ResourceType=volume,Tags=[{Key=Deployment,Value=$deployment},{Key=Name,Value=$deployment-restored}]" --query VolumeId --output text)
        echo "Restored volume: $restored (original volume retained: $volume_id)"
        aws_cli ec2 wait volume-available --volume-ids "$restored"
        aws_cli ec2 attach-volume --volume-id "$restored" --instance-id "$instance" --device /dev/sdg >/dev/null
        aws_cli ec2 wait volume-in-use --volume-ids "$restored"
        stage=$(mktemp -d /mnt/nntmux-restore.XXXXXXXX)
        stage_config=$(mktemp "$STATE_ROOT/restore-host.XXXXXXXX")
        jq --arg id "$restored" '.data_volume_id = $id' "$HOST_CONFIG" > "$stage_config"
        HOST_CONFIG="$stage_config" DATA_ROOT="$stage" "$ASSET_ROOT/volume.sh" mount
        [[ -f "$stage/install/install.lock" && -f "$stage/recovery/config/identity.json" && -f "$stage/recovery/release.env" ]] || fail "Restored volume lacks deployment recovery metadata; inspect $stage."
        cmp -s "$CONFIG_DIRECTORY/identity.json" "$stage/recovery/config/identity.json" || fail "Restored application key or database identity differs; inspect $stage before recovery."
        if [[ ${3:-} != --activate ]]; then
            echo "Verified recovery volume is mounted at $stage. Use a new restore with --activate to switch; clean up this staged volume manually."
            exit 0
        fi
        # Preserve the current deployment and stop all services before any volume switch.
        stop_writers
        snapshot before-restore mariadb redis manticore web caddy
        compose stop
        recovery_config=$(mktemp -d "$STATE_ROOT/config.XXXXXXXX")
        cp "$stage/recovery/config/"{application.env,database.env,identity.json} "$recovery_config/"
        recovered_image=$(sed -n 's/^APP_IMAGE=//p' "$stage/recovery/release.env")
        [[ "${recovered_image%@*}" == "$repository" && "${recovered_image##*@}" =~ ^sha256:[a-f0-9]{64}$ ]] || fail 'Recovery metadata has an unexpected image.'
        chown 33:33 "$recovery_config/application.env"
        chmod 0600 "$recovery_config/application.env"
        aws_cli ecr get-login-password | docker login --username AWS --password-stdin "${repository%%/*}" >/dev/null
        docker pull "$recovered_image"
        systemctl stop docker.service docker.socket containerd.service
        HOST_CONFIG="$stage_config" DATA_ROOT="$stage" "$ASSET_ROOT/volume.sh" unmount
        "$ASSET_ROOT/volume.sh" unmount
        aws_cli ec2 detach-volume --volume-id "$volume_id" --instance-id "$instance" >/dev/null
        aws_cli ec2 wait volume-available --volume-ids "$volume_id"
        aws_cli ec2 detach-volume --volume-id "$restored" --instance-id "$instance" >/dev/null
        aws_cli ec2 wait volume-available --volume-ids "$restored"
        aws_cli ec2 attach-volume --volume-id "$restored" --instance-id "$instance" --device /dev/sdf >/dev/null
        aws_cli ec2 wait volume-in-use --volume-ids "$restored"
        cp "$stage_config" "$HOST_CONFIG.new"
        mv "$HOST_CONFIG.new" "$HOST_CONFIG"
        volume_id="$restored"
        "$ASSET_ROOT/volume.sh" mount
        APP_IMAGE="$recovered_image"
        CONFIG_DIRECTORY="$recovery_config"
        ACTIVE_ENV="$recovery_config/release.env"
        write_env
        record_release
        finish_restore
        echo 'Recovery activated. Reconcile Terraform state before the next apply; retain the original volume until recovery is accepted.'
}
