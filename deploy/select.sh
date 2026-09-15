#!/usr/bin/env bash
# Select a separate infrastructure state and transport; never switch an existing host implicitly.
set -euo pipefail
umask 077
repository_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
[[ $# -ge 2 ]] || { echo "Supply a provider and operation." >&2; exit 1; }
provider=${1:-}
operation=${2:-}
case "$provider" in aws|hetzner|ovh) ;; *) echo 'Usage: select.sh aws|hetzner|ovh terraform|bootstrap|volume-initialize|initialize|release|rollback|backup|restore|start|stop [arguments]' >&2; exit 1 ;; esac
shift 2
root="$repository_root/infra/terraform/$provider"
if [[ "$operation" == terraform ]]; then exec terraform -chdir="$root" "$@"; fi
case "$operation" in bootstrap|volume-initialize|initialize|release|rollback|backup|restore|start|stop) ;; *) echo 'Unsupported deployment operation.' >&2; exit 1 ;; esac
outputs=$(terraform -chdir="$root" output -json)
host_config=$(jq -ec '.host_config.value' <<< "$outputs")
[[ "$(jq -er '.provider' <<< "$host_config")" == "$provider" ]] || { echo 'Infrastructure state belongs to another provider.' >&2; exit 1; }
if [[ "$operation" == volume-initialize ]]; then
    printf -v command '%q ' /opt/nntmux/volume.sh initialize "$@"
    command+=' && systemctl start nntmux-data.service && systemctl restart docker.service'
else
    printf -v command '%q ' /opt/nntmux/deploy.sh "$operation" "$@"
fi
if [[ "$provider" == aws ]]; then
    [[ "$operation" != bootstrap ]] || { echo 'AWS host bootstrap is managed by Terraform cloud-init.' >&2; exit 1; }
    region=$(jq -er '.region' <<< "$host_config")
    instance=$(jq -er '.instance_id.value' <<< "$outputs")
    parameters=$(jq -nc --arg command "$command" '{commands:[$command]}')
    id=$(aws --region "$region" ssm send-command --instance-ids "$instance" --document-name AWS-RunShellScript --parameters "$parameters" --query Command.CommandId --output text)
    echo "Systems Manager command: $id"
    # The standard AWS waiter ends after 100 seconds; long backup/restore operations need longer.
    for ((attempt=0; attempt<360; attempt++)); do
        result=$(aws --region "$region" ssm get-command-invocation --command-id "$id" --instance-id "$instance" --output json 2>/dev/null) || { sleep 5; continue; }
        status=$(jq -er '.Status' <<< "$result")
        case "$status" in
            Success) jq -r '.StandardOutputContent' <<< "$result"; exit 0 ;;
            Pending|InProgress|Delayed) sleep 5 ;;
            *) jq -r '.StandardOutputContent, .StandardErrorContent, .Status' <<< "$result" >&2; exit 1 ;;
        esac
    done
    echo 'Command is still running; inspect its status through Systems Manager.' >&2
    exit 1
fi
address=$(jq -er '.static_ip.value' <<< "$outputs")
user=$(jq -er '.ssh_user.value' <<< "$outputs")
[[ "$address" =~ ^[0-9.]+$ && "$user" =~ ^[a-z]+$ ]] || { echo 'Invalid administrator connection parameters.' >&2; exit 1; }
target="$user@$address"
ssh_options=(-o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20)
if [[ "$operation" == bootstrap ]]; then
    [[ $# -eq 0 ]] || { echo 'Bootstrap takes no arguments.' >&2; exit 1; }
    stage=$(mktemp -d)
    trap 'rm -rf "$stage"' EXIT
    cp -R "$repository_root/deploy/cloud/." "$stage/"
    rm -rf "$stage/tests"
    printf '%s\n' "$host_config" > "$stage/host.json"
    tar -C "$stage" -czf - . | ssh "${ssh_options[@]}" "$target" 'sudo -n cloud-init status --wait >/dev/null && sudo -n install -d -m 0755 /opt/nntmux /etc/nntmux && sudo -n tar -xzf - -C /opt/nntmux && if ! test -f /etc/nntmux/host.json; then sudo -n install -m 0600 /opt/nntmux/host.json /etc/nntmux/host.json; fi && sudo -n rm /opt/nntmux/host.json && sudo -n chmod 0755 /opt/nntmux/*.sh /opt/nntmux/providers/*.sh && sudo -n cp /opt/nntmux/nntmux*.service /opt/nntmux/nntmux-backup.timer /etc/systemd/system/ && sudo -n install -d /etc/systemd/system/docker.service.d && sudo -n cp /opt/nntmux/docker-data.conf /etc/systemd/system/docker.service.d/nntmux-data.conf && sudo -n install -d /etc/docker && sudo -n install -m 0600 /opt/nntmux/docker-daemon.json /etc/docker/daemon.json && sudo -n systemctl stop docker.service docker.socket containerd.service && sudo -n systemctl daemon-reload && sudo -n systemctl enable docker nntmux-data.service nntmux.service'
else
    printf -v remote 'sudo -n bash -c %q' "$command"
    # Arguments were shell-quoted above for intentional execution on the remote host.
    # shellcheck disable=SC2029
    ssh "${ssh_options[@]}" "$target" "$remote"
fi
