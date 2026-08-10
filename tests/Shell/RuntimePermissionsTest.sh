#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
fixture_root="$(mktemp -d)"
outside_target="$(mktemp)"

cleanup() {
    rm -rf -- "$fixture_root"
    rm -f -- "$outside_target"
}
trap cleanup EXIT

fail() {
    echo "FAIL: $*" >&2
    exit 1
}

assert_mode() {
    local expected="$1"
    local path="$2"
    local actual

    actual="$(stat -c '%a' "$path")"
    [[ "$actual" == "$expected" ]] || fail "$path mode is $actual, expected $expected"
}

mkdir -p "$fixture_root"/{app,nested,public/build/assets,vendor/bin,storage/framework/views,storage/logs,bootstrap/cache,resources,routes,config}
git -C "$fixture_root" init -q

printf '<?php\n' > "$fixture_root/app/OwnerOnly.php"
printf '#!/usr/bin/env sh\nexit 0\n' > "$fixture_root/artisan"
printf 'autoload\n' > "$fixture_root/vendor/autoload.php"
printf '#!/usr/bin/env sh\nexit 0\n' > "$fixture_root/vendor/bin/tool"
printf '{}\n' > "$fixture_root/public/build/manifest.json"
printf 'asset\n' > "$fixture_root/public/build/assets/app.js"
printf '<?php\n' > "$fixture_root/storage/framework/views/compiled.php"
printf 'log\n' > "$fixture_root/storage/logs/app.log"
printf '#!/usr/bin/env sh\nexit 0\n' > "$fixture_root/storage/tracked-tool"
printf 'secret\n' > "$fixture_root/.env"
printf 'outside\n' > "$outside_target"
ln -s "$outside_target" "$fixture_root/app/external-link"

chmod 0700 "$fixture_root/artisan" "$fixture_root/vendor/bin/tool" "$fixture_root/storage/tracked-tool"
git -C "$fixture_root" add app artisan resources routes config bootstrap storage/tracked-tool
git -C "$fixture_root" update-index --chmod=+x artisan storage/tracked-tool

find "$fixture_root/app" "$fixture_root/public/build" "$fixture_root/vendor" "$fixture_root/storage" "$fixture_root/bootstrap/cache" -type d -exec chmod 0700 {} +
find "$fixture_root/app" "$fixture_root/public/build" "$fixture_root/vendor" "$fixture_root/storage" "$fixture_root/bootstrap/cache" -type f -exec chmod 0600 {} +
chmod 0700 "$fixture_root/vendor/bin/tool"
chmod 0600 "$fixture_root/.env"
chmod 0600 "$outside_target"

identity_user="$(id -un)"
identity_group="$(id -gn)"

if DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" check "$fixture_root" >/dev/null 2>&1; then
    fail 'check unexpectedly passed before normalization'
fi

DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" normalize "$fixture_root"

assert_mode 644 "$fixture_root/app/OwnerOnly.php"
assert_mode 755 "$fixture_root/artisan"
assert_mode 755 "$fixture_root/public/build"
assert_mode 755 "$fixture_root/public/build/assets"
assert_mode 644 "$fixture_root/public/build/manifest.json"
assert_mode 644 "$fixture_root/public/build/assets/app.js"
assert_mode 644 "$fixture_root/vendor/autoload.php"
assert_mode 755 "$fixture_root/vendor/bin/tool"
assert_mode 2775 "$fixture_root/storage/framework/views"
assert_mode 664 "$fixture_root/storage/framework/views/compiled.php"
assert_mode 2775 "$fixture_root/storage/logs"
assert_mode 660 "$fixture_root/storage/logs/app.log"
assert_mode 755 "$fixture_root/storage/tracked-tool"
assert_mode 2775 "$fixture_root/bootstrap/cache"
assert_mode 640 "$fixture_root/.env"
assert_mode 600 "$outside_target"
[[ -L "$fixture_root/app/external-link" ]] || fail 'source symlink was replaced'

DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" check "$fixture_root"

chmod 0400 "$fixture_root/storage/logs/app.log"
if DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" check "$fixture_root" >/dev/null 2>&1; then
    fail 'check accepted a non-writable generated storage file'
fi
DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" normalize "$fixture_root" >/dev/null

second_run="$({ DEPLOYMENT_OWNER="$identity_user" APPLICATION_USER="$identity_user" APPLICATION_GROUP="$identity_group" \
    "$repository_root/scripts/runtime-permissions.sh" normalize "$fixture_root"; } 2>&1)"
[[ "$second_run" == *'corrected 0 path(s)'* ]] || fail "second normalization was not clean: $second_run"

if "$repository_root/scripts/runtime-permissions.sh" check / >/dev/null 2>&1; then
    fail 'unsafe root was accepted'
fi

if "$repository_root/scripts/runtime-permissions.sh" check "$fixture_root/nested" >/dev/null 2>&1; then
    fail 'non-repository target was accepted'
fi

echo 'Runtime permission regression passed.'
