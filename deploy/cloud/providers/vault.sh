#!/usr/bin/env bash
# Fetch Vault KV v2 without exposing its token in process arguments or logs.
vault_fetch() {
    local address path token_file directory permissions token
    address=$(jq -er '.vault_address' "$HOST_CONFIG")
    path=$(jq -er '.vault_secret_path' "$HOST_CONFIG")
    token_file=${VAULT_TOKEN_FILE:-/etc/nntmux/vault-token}
    [[ "$address" == https://* && "$path" =~ ^[a-zA-Z0-9/_-]+$ ]] || { echo 'Invalid Vault endpoint.' >&2; return 1; }
    [[ -f "$token_file" && ! -L "$token_file" ]] || { echo 'Missing Vault token file.' >&2; return 1; }
    permissions=$(stat -c '%a' "$token_file")
    [[ "$permissions" == 600 && "$(stat -c '%u' "$token_file")" == "$EUID" ]] || { echo 'Vault token must be owned by the operator with mode 0600.' >&2; return 1; }
    token=$(cat "$token_file")
    [[ "$token" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Invalid Vault token file." >&2; return 1; }
    directory=$(mktemp -d "${RUNTIME_ROOT:-/run}/nntmux-vault.XXXXXXXX") || return 1
    chmod 0700 "$directory"
    printf 'X-Vault-Token: %s\n' "$token" > "$directory/header"
    if ! curl --fail --silent --show-error --proto '=https' --header "@$directory/header" "$address/v1/$path" > "$directory/response"; then
        rm -rf "$directory"
        echo 'Unable to retrieve deployment configuration from Vault.' >&2
        return 1
    fi
    if ! jq -e '.data.data | type == "object"' "$directory/response" >/dev/null; then
        rm -rf "$directory"
        echo 'Invalid Vault KV v2 response.' >&2
        return 1
    fi
    cat "$directory/response"
    rm -rf "$directory"
}

vault_volume_key() {
    local response
    response=$(vault_fetch) || return 1
    jq -er '.data.data.volume_key' <<< "$response" | base64 --decode
}
