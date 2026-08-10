#!/usr/bin/env bash

set -euo pipefail

append_phpunit_cache=false
print_environment=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --phpunit-cache)
            append_phpunit_cache=true
            shift
            ;;
        --print-environment)
            print_environment=true
            shift
            ;;
        *)
            break
            ;;
    esac
done

test_cache_root="$(mktemp -d "${TMPDIR:-/tmp}/nntmux-tests.XXXXXXXX")"

cleanup() {
    case "$test_cache_root" in
        "${TMPDIR:-/tmp}"/nntmux-tests.*)
            rm -rf -- "$test_cache_root"
            ;;
        *)
            echo "Refusing to remove unsafe test cache path: $test_cache_root" >&2
            return 1
            ;;
    esac
}
trap cleanup EXIT HUP INT TERM

export NNTMUX_TEST_CACHE_ROOT="$test_cache_root"
export VIEW_COMPILED_PATH="$test_cache_root/views"
export APP_CONFIG_CACHE="$test_cache_root/bootstrap/config.php"
export APP_ROUTES_CACHE="$test_cache_root/bootstrap/routes-v7.php"
export APP_EVENTS_CACHE="$test_cache_root/bootstrap/events.php"
export APP_PACKAGES_CACHE="$test_cache_root/bootstrap/packages.php"
export APP_SERVICES_CACHE="$test_cache_root/bootstrap/services.php"
export PHPUNIT_CACHE_DIRECTORY="$test_cache_root/phpunit"

mkdir -p "$VIEW_COMPILED_PATH" "$test_cache_root/bootstrap" "$PHPUNIT_CACHE_DIRECTORY"

if [[ "$print_environment" == true ]]; then
    printf '%s\n' \
        "$NNTMUX_TEST_CACHE_ROOT" \
        "$VIEW_COMPILED_PATH" \
        "$APP_CONFIG_CACHE" \
        "$APP_ROUTES_CACHE" \
        "$APP_EVENTS_CACHE" \
        "$APP_PACKAGES_CACHE" \
        "$APP_SERVICES_CACHE" \
        "$PHPUNIT_CACHE_DIRECTORY"
    exit 0
fi

if [[ $# -eq 0 ]]; then
    echo 'Usage: scripts/run-tests-isolated.sh [--phpunit-cache] command [arguments...]' >&2
    exit 2
fi

if [[ "$append_phpunit_cache" == true ]]; then
    "$@" --cache-directory "$PHPUNIT_CACHE_DIRECTORY"
else
    "$@"
fi
