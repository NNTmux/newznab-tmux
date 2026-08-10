#!/usr/bin/env bash

set -uo pipefail

action="${1:-}"
requested_root="${2:-$(pwd -P)}"
script_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
failures=0
corrections=0

error() {
    echo "ERROR: $*" >&2
    failures=$((failures + 1))
}

fatal() {
    echo "ERROR: $*" >&2
    exit 2
}

[[ "$action" == normalize || "$action" == normalize-build || "$action" == check || "$action" == check-source ]] || fatal 'usage: runtime-permissions.sh normalize|normalize-build|check|check-source [repository-root]'
[[ -n "$requested_root" ]] || fatal 'repository root must not be empty'
[[ -e "$requested_root" ]] || fatal "repository root does not exist: $requested_root"

repository_root="$(realpath -e -- "$requested_root")" || fatal "cannot resolve repository root: $requested_root"
home_root="$(realpath -m -- "${HOME:-/nonexistent-home}")"

[[ "$repository_root" != / ]] || fatal 'refusing filesystem root'
[[ "$repository_root" != "$home_root" ]] || fatal 'refusing home directory'

git_root="$(git -C "$repository_root" rev-parse --show-toplevel 2>/dev/null)" || fatal "not a Git repository: $repository_root"
git_root="$(realpath -e -- "$git_root")" || fatal 'cannot resolve Git repository root'
[[ "$repository_root" == "$git_root" ]] || fatal "target is not the Git repository root: $repository_root"

resolve_fpm_identity() {
    local field="$1"
    local value=''
    local config

    for config in /etc/php/*/fpm/pool.d/*.conf; do
        [[ -f "$config" ]] || continue
        value="$(awk -F= -v field="$field" '
            $1 ~ "^[[:space:]]*" field "[[:space:]]*$" {
                gsub(/^[[:space:]]+|[[:space:]]+$/, "", $2)
                print $2
            }
        ' "$config" | tail -n 1)"
        [[ -n "$value" ]] && printf '%s\n' "$value"
    done | tail -n 1
}

script_repository_root="$(realpath -e -- "$script_root/..")" || fatal 'cannot resolve script repository root'

if [[ "$repository_root" == "$script_repository_root" ]]; then
    if [[ -v DEPLOYMENT_OWNER || -v APPLICATION_USER || -v APPLICATION_GROUP ]]; then
        fatal 'identity overrides are forbidden for the live checkout'
    fi

    repository_owner_uid="$(stat -c '%u' "$repository_root")"
    deployment_owner="$(getent passwd "$repository_owner_uid" | cut -d: -f1)"
    [[ -n "$deployment_owner" ]] || fatal "repository owner does not resolve to a local user: $repository_owner_uid"
    application_user="$deployment_owner"
    application_group='www-data'
else
    deployment_owner="${DEPLOYMENT_OWNER:-$(stat -c '%u' "$repository_root")}"

    detected_application_user=''
    detected_application_group=''
    if [[ "$(id -u)" == 0 ]]; then
        detected_application_user="$(resolve_fpm_identity user)"
        detected_application_group="$(resolve_fpm_identity group)"
    fi
    application_user="${APPLICATION_USER:-$detected_application_user}"
    application_user="${application_user:-$(id -un)}"
    application_group="${APPLICATION_GROUP:-$detected_application_group}"
    application_group="${application_group:-$(id -gn "$application_user" 2>/dev/null || true)}"
fi

getent passwd "$deployment_owner" >/dev/null 2>&1 || fatal "deployment owner does not exist: $deployment_owner"
getent passwd "$application_user" >/dev/null 2>&1 || fatal "application user does not exist: $application_user"
getent group "$application_group" >/dev/null 2>&1 || fatal "application group does not exist: $application_group"

owner_uid="$(id -u "$deployment_owner")"
group_gid="$(getent group "$application_group" | cut -d: -f3)"
application_uid="$(id -u "$application_user")"

describe() {
    local path="$1"
    stat -c 'mode=%a owner=%U(%u) group=%G(%g)' "$path" 2>/dev/null || printf 'unavailable'
}

set_mode() {
    local expected="$1"
    local path="$2"
    local observed

    [[ -L "$path" ]] && return
    observed="$(stat -c '%a' "$path")" || { error "cannot inspect $path"; return; }
    [[ "$observed" == "${expected#0}" ]] && return

    if chmod "$expected" -- "$path"; then
        corrections=$((corrections + 1))
    else
        error "cannot set $path to mode ${expected#0}; observed $(describe "$path")"
    fi
}

set_owner_group() {
    local path="$1"
    local observed_uid observed_gid

    [[ -L "$path" ]] && return
    observed_uid="$(stat -c '%u' "$path")" || { error "cannot inspect owner for $path"; return; }
    observed_gid="$(stat -c '%g' "$path")" || { error "cannot inspect group for $path"; return; }
    [[ "$observed_uid" == "$owner_uid" && "$observed_gid" == "$group_gid" ]] && return

    if chown "$deployment_owner:$application_group" -- "$path"; then
        corrections=$((corrections + 1))
    else
        error "cannot set ownership on $path to $deployment_owner:$application_group; observed $(describe "$path")"
    fi
}

require_mode() {
    local expected="$1"
    local path="$2"
    local observed

    [[ -L "$path" ]] && return
    observed="$(stat -c '%a' "$path")" || { error "cannot inspect $path"; return; }
    [[ "$observed" == "${expected#0}" ]] || error "$path has $(describe "$path"); expected mode ${expected#0}; run 'make fix-permissions'"
}

require_owner_group() {
    local path="$1"
    local observed_uid observed_gid

    [[ -L "$path" ]] && return
    observed_uid="$(stat -c '%u' "$path")" || { error "cannot inspect owner for $path"; return; }
    observed_gid="$(stat -c '%g' "$path")" || { error "cannot inspect group for $path"; return; }
    [[ "$observed_uid" == "$owner_uid" && "$observed_gid" == "$group_gid" ]] || \
        error "$path has $(describe "$path"); expected owner=$deployment_owner group=$application_group; run 'make fix-permissions'"
}

run_as_application() {
    if [[ "$(id -u)" == "$application_uid" ]]; then
        "$@"
    elif [[ "$(id -u)" == 0 ]] && command -v runuser >/dev/null 2>&1; then
        runuser -u "$application_user" -- "$@"
    elif [[ "$(id -u)" == 0 ]] && command -v su >/dev/null 2>&1; then
        su -s /bin/sh "$application_user" -c 'exec "$@"' sh "$@"
    else
        return 125
    fi
}

require_application_access() {
    local access_flag="$1"
    local expectation="$2"
    local path="$3"

    if ! run_as_application test "$access_flag" "$path"; then
        error "$path is not $expectation by application user $application_user; observed $(describe "$path"); run 'make fix-permissions'"
    fi
}

require_tree_access() {
    local path="$1"
    local access_kind="$2"
    local inaccessible_path status

    case "$access_kind" in
        runtime-source)
            inaccessible_path="$(run_as_application find -P "$path" -path "$repository_root/bootstrap/cache" -prune -o \( -type d ! -executable -o -type f ! -readable \) -print -quit 2>/dev/null)"
            status=$?
            ;;
        readable)
            inaccessible_path="$(run_as_application find -P "$path" \( -type d ! -executable -o -type f ! -readable \) -print -quit 2>/dev/null)"
            status=$?
            ;;
        runtime-writable)
            inaccessible_path="$(run_as_application find -P "$path" -type d \( ! -writable -o ! -executable \) -print -quit 2>/dev/null)"
            status=$?
            ;;
        *)
            error "unknown access check: $access_kind"
            return
            ;;
    esac

    if [[ "$status" -ne 0 ]]; then
        error "could not execute $access_kind check as application user $application_user for $path"
    elif [[ -n "$inaccessible_path" ]]; then
        error "$inaccessible_path fails the $access_kind check for application user $application_user; observed $(describe "$inaccessible_path"); run 'make fix-permissions'"
    fi
}

source_roots=(app bootstrap config resources routes)
entry_points=(artisan public/index.php server.php)
declare -A tracked_runtime_modes=()

while IFS= read -r -d '' index_record; do
    index_mode="${index_record%% *}"
    relative_path="${index_record#*$'\t'}"
    if [[ "$index_mode" == 100755 ]]; then
        tracked_runtime_modes["$repository_root/$relative_path"]=0755
    else
        tracked_runtime_modes["$repository_root/$relative_path"]=0644
    fi
done < <(git -C "$repository_root" ls-files -z --stage -- storage bootstrap/cache)

normalize_tree() {
    local root="$1"
    local directory file mode

    [[ -e "$root" ]] || return
    while IFS= read -r -d '' directory; do
        set_mode 0755 "$directory"
    done < <(find -P "$root" -type d ! -perm 0755 -print0)

    while IFS= read -r -d '' file; do
        set_mode 0755 "$file"
    done < <(find -P "$root" -type f -perm /0111 ! -perm 0755 -print0)
    while IFS= read -r -d '' file; do
        set_mode 0644 "$file"
    done < <(find -P "$root" -type f ! -perm /0111 ! -perm 0644 -print0)
}

normalize_runtime_tree() {
    local root="$1"
    local file directory

    [[ -e "$root" ]] || return
    while IFS= read -r -d '' directory; do
        set_owner_group "$directory"
    done < <(find -P "$root" -type d \( ! -user "$deployment_owner" -o ! -group "$application_group" \) -print0)
    while IFS= read -r -d '' directory; do
        set_mode 02775 "$directory"
    done < <(find -P "$root" -type d ! -perm 02775 -print0)
    while IFS= read -r -d '' file; do
        set_owner_group "$file"
    done < <(find -P "$root" -type f \( ! -user "$deployment_owner" -o ! -group "$application_group" \) -print0)
    while IFS= read -r -d '' file; do
        [[ -v 'tracked_runtime_modes[$file]' ]] && continue
        if [[ "$file" == "$repository_root/storage/framework/views/"* ]]; then
            set_mode 0664 "$file"
        else
            set_mode 0660 "$file"
        fi
    done < <(find -P "$root" -type f \( -path "$repository_root/storage/framework/views/*" ! -perm 0664 -o ! -path "$repository_root/storage/framework/views/*" ! -perm 0660 \) -print0)
}

normalize_build_tree() {
    if [[ ! -d "$repository_root/public/build" ]]; then
        error "$repository_root/public/build is missing after frontend build"
        return
    fi

    while IFS= read -r -d '' directory; do set_mode 0755 "$directory"; done < <(find -P "$repository_root/public/build" -type d -print0)
    while IFS= read -r -d '' file; do set_mode 0644 "$file"; done < <(find -P "$repository_root/public/build" -type f -print0)
}

if [[ "$action" == normalize-build ]]; then
    normalize_build_tree
    if [[ -d "$repository_root/public/build" ]]; then
        [[ -f "$repository_root/public/build/manifest.json" ]] || error "$repository_root/public/build/manifest.json is missing after frontend build"
    fi
elif [[ "$action" == normalize ]]; then
    for relative_root in "${source_roots[@]}"; do
        [[ -d "$repository_root/$relative_root" ]] || continue
        while IFS= read -r -d '' directory; do
            set_mode 0755 "$directory"
        done < <(find -P "$repository_root/$relative_root" -path "$repository_root/bootstrap/cache" -prune -o -type d ! -perm 0755 -print0)
    done

    while IFS= read -r -d '' index_record; do
        index_mode="${index_record%% *}"
        relative_path="${index_record#*$'\t'}"
        path="$repository_root/$relative_path"
        [[ -f "$path" && ! -L "$path" ]] || continue
        if [[ "$index_mode" == 100755 ]]; then
            set_mode 0755 "$path"
        else
            set_mode 0644 "$path"
        fi
    done < <(git -C "$repository_root" ls-files -z --stage -- "${source_roots[@]}" "${entry_points[@]}" ':(exclude)bootstrap/cache/**')

    normalize_tree "$repository_root/vendor"

    if [[ -d "$repository_root/public/build" ]]; then
        normalize_build_tree
    fi

    normalize_runtime_tree "$repository_root/storage"
    normalize_runtime_tree "$repository_root/bootstrap/cache"

    for path in "${!tracked_runtime_modes[@]}"; do
        [[ -f "$path" && ! -L "$path" ]] || continue
        set_mode "${tracked_runtime_modes[$path]}" "$path"
    done

    if [[ -f "$repository_root/.env" && ! -L "$repository_root/.env" ]]; then
        set_owner_group "$repository_root/.env"
        set_mode 0640 "$repository_root/.env"
    fi
else
    for relative_root in "${source_roots[@]}"; do
        [[ -d "$repository_root/$relative_root" ]] || continue
        while IFS= read -r -d '' directory; do
            require_mode 0755 "$directory"
        done < <(find -P "$repository_root/$relative_root" -path "$repository_root/bootstrap/cache" -prune -o -type d ! -perm 0755 -print0)
        require_tree_access "$repository_root/$relative_root" runtime-source
    done

    declare -A tracked_expected_modes=()
    tracked_paths=()
    while IFS= read -r -d '' index_record; do
        index_mode="${index_record%% *}"
        relative_path="${index_record#*$'\t'}"
        path="$repository_root/$relative_path"
        [[ -f "$path" && ! -L "$path" ]] || continue
        if [[ "$index_mode" == 100755 ]]; then expected_mode=0755; else expected_mode=0644; fi
        tracked_expected_modes["$path"]="${expected_mode#0}"
        tracked_paths+=("$path")
    done < <(git -C "$repository_root" ls-files -z --stage -- "${source_roots[@]}" "${entry_points[@]}" ':(exclude)bootstrap/cache/**')

    if [[ "${#tracked_paths[@]}" -gt 0 ]]; then
        while IFS= read -r -d '' stat_record; do
            observed_mode="${stat_record%%$'\t'*}"
            path="${stat_record#*$'\t'}"
            expected_mode="${tracked_expected_modes[$path]}"
            [[ "$observed_mode" == "$expected_mode" ]] || error "$path has $(describe "$path"); expected mode $expected_mode from the Git index; run 'make fix-permissions'"
        done < <(stat --printf '%a\t%n\0' -- "${tracked_paths[@]}")
    fi

    if [[ "$action" == check-source ]]; then
        if [[ "$failures" -gt 0 ]]; then
            echo "Tracked runtime source permission check failed with $failures problem(s)." >&2
            exit 1
        fi

        echo 'Tracked runtime source permissions passed.'
        exit 0
    fi

    if [[ -f "$repository_root/vendor/autoload.php" ]]; then
        require_tree_access "$repository_root/vendor" readable
    else
        error "$repository_root/vendor/autoload.php is missing; run 'make composer-install'"
    fi

    if [[ -d "$repository_root/public/build" ]]; then
        while IFS= read -r -d '' directory; do require_mode 0755 "$directory"; done < <(find -P "$repository_root/public/build" -type d ! -perm 0755 -print0)
        while IFS= read -r -d '' file; do require_mode 0644 "$file"; done < <(find -P "$repository_root/public/build" -type f ! -perm 0644 -print0)
        require_tree_access "$repository_root/public/build" readable
    fi
    if [[ -f "$repository_root/public/build/manifest.json" ]]; then
        require_mode 0644 "$repository_root/public/build/manifest.json"
        require_application_access -r readable "$repository_root/public/build/manifest.json"
    elif [[ -d "$repository_root/public/build" ]]; then
        error "$repository_root/public/build/manifest.json is missing; run 'make npm-build'"
    fi

    for runtime_root in "$repository_root/storage" "$repository_root/bootstrap/cache"; do
        [[ -d "$runtime_root" ]] || { error "$runtime_root is missing; run 'make fix-permissions'"; continue; }
        while IFS= read -r -d '' directory; do
            require_owner_group "$directory"
        done < <(find -P "$runtime_root" -type d \( ! -user "$deployment_owner" -o ! -group "$application_group" \) -print0)
        while IFS= read -r -d '' directory; do
            require_mode 02775 "$directory"
        done < <(find -P "$runtime_root" -type d ! -perm 02775 -print0)
        require_tree_access "$runtime_root" runtime-writable
        while IFS= read -r -d '' runtime_file; do
            [[ -v 'tracked_runtime_modes[$runtime_file]' ]] && continue
            error "$runtime_file is not readable/writable by application user $application_user; observed $(describe "$runtime_file"); run 'make fix-permissions'"
        done < <(run_as_application find -P "$runtime_root" -type f \( ! -readable -o ! -writable \) -print0 2>/dev/null)
    done

    if [[ -d "$repository_root/storage/framework/views" ]]; then
        while IFS= read -r -d '' file; do
            require_owner_group "$file"
        done < <(find -P "$repository_root/storage/framework/views" -type f \( ! -user "$deployment_owner" -o ! -group "$application_group" \) -print0)
        while IFS= read -r -d '' file; do
            [[ -v 'tracked_runtime_modes[$file]' ]] && continue
            require_mode 0664 "$file"
        done < <(find -P "$repository_root/storage/framework/views" -type f ! -perm 0664 -print0)
        inaccessible_view="$(run_as_application find -P "$repository_root/storage/framework/views" -type f ! -name .gitignore \( ! -readable -o ! -writable \) -print -quit 2>/dev/null)"
        view_access_status=$?
        if [[ "$view_access_status" -ne 0 ]]; then
            error "could not check compiled views as application user $application_user"
        elif [[ -n "$inaccessible_view" ]]; then
            error "$inaccessible_view is not readable/writable by application user $application_user; observed $(describe "$inaccessible_view"); run 'make fix-permissions'"
        fi
    fi

    for path in "${!tracked_runtime_modes[@]}"; do
        [[ -f "$path" && ! -L "$path" ]] || continue
        require_mode "${tracked_runtime_modes[$path]}" "$path"
    done

    if [[ -f "$repository_root/.env" && ! -L "$repository_root/.env" ]]; then
        require_owner_group "$repository_root/.env"
        require_mode 0640 "$repository_root/.env"
        require_application_access -r readable "$repository_root/.env"
        [[ $((8#$(stat -c '%a' "$repository_root/.env") & 8#004)) -eq 0 ]] || error "$repository_root/.env is world-readable; run 'make fix-permissions'"
    else
        error "$repository_root/.env is missing"
    fi

    isolation_paths="$($script_root/run-tests-isolated.sh --print-environment 2>/dev/null)" || error 'cannot resolve isolated test cache paths'
    while IFS= read -r isolated_path; do
        [[ -n "$isolated_path" ]] || continue
        case "$isolated_path" in
            "$repository_root/storage/framework/views"|"$repository_root/bootstrap/cache"/*|"$repository_root/.phpunit.cache"*)
                error "test cache path resolves to live path: $isolated_path"
                ;;
        esac
    done <<< "$isolation_paths"
fi

if [[ "$failures" -gt 0 ]]; then
    echo "Permission $action failed with $failures problem(s)." >&2
    exit 1
fi

if [[ "$action" == normalize || "$action" == normalize-build ]]; then
    echo "Permission normalization corrected $corrections path(s)."
else
    echo "Permission and test-isolation checks passed for application user $application_user."
fi
