#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
result_file="$(mktemp)"
trap 'rm -f -- "$result_file"' EXIT

"$repository_root/scripts/run-tests-isolated.sh" --phpunit-cache bash -c '
    set -eu
    printf "%s\n" \
        "$NNTMUX_TEST_CACHE_ROOT" \
        "$VIEW_COMPILED_PATH" \
        "$APP_CONFIG_CACHE" \
        "$APP_ROUTES_CACHE" \
        "$APP_EVENTS_CACHE" \
        "$APP_PACKAGES_CACHE" \
        "$APP_SERVICES_CACHE" \
        "$PHPUNIT_CACHE_DIRECTORY" > "$1"
    touch "$VIEW_COMPILED_PATH/probe.php"
' _ "$result_file"

mapfile -t paths < "$result_file"
[[ "${#paths[@]}" -eq 8 ]] || { echo 'FAIL: expected all isolated paths' >&2; exit 1; }

test_root="${paths[0]}"
[[ ! -e "$test_root" ]] || { echo "FAIL: $test_root was not cleaned" >&2; exit 1; }

live_paths=(
    "$repository_root/storage/framework/views"
    "$repository_root/bootstrap/cache/config.php"
    "$repository_root/bootstrap/cache/routes-v7.php"
    "$repository_root/bootstrap/cache/events.php"
    "$repository_root/bootstrap/cache/packages.php"
    "$repository_root/bootstrap/cache/services.php"
    "$repository_root/.phpunit.cache"
)

for isolated_path in "${paths[@]:1}"; do
    [[ "$isolated_path" == "$test_root"/* ]] || {
        echo "FAIL: $isolated_path is outside $test_root" >&2
        exit 1
    }

    for live_path in "${live_paths[@]}"; do
        [[ "$isolated_path" != "$live_path" ]] || {
            echo "FAIL: isolated path matches live path $live_path" >&2
            exit 1
        }
    done
done

echo 'Test cache isolation regression passed.'
