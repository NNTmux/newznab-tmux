#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
cd "$repository_root"
before_snapshot="$(mktemp)"
after_snapshot="$(mktemp)"

cleanup() {
    rm -f -- "$before_snapshot" "$after_snapshot"
}
trap cleanup EXIT

snapshot_live_caches() {
    local output="$1"
    local path relative_path checksum

    : > "$output"
    while IFS= read -r -d '' path; do
        relative_path="${path#"$repository_root/"}"
        if [[ -f "$path" ]]; then
            checksum="$(sha256sum "$path" | cut -d' ' -f1)"
        else
            checksum='-'
        fi
        stat --printf '%n\0%F\0%a\0%u\0%g\0%s\0%y\0' "$relative_path" >> "$output"
        printf '%s\0' "$checksum" >> "$output"
    done < <(find -P "$repository_root/storage/framework/views" "$repository_root/bootstrap/cache" -print0 | sort -z)
}

snapshot_live_caches "$before_snapshot"

"$repository_root/sail" artisan test --compact \
    tests/Feature/AdminTmuxSettingsControllerTest.php \
    tests/Unit/Http/Requests/Admin/UpdateTmuxSettingsRequestTest.php

snapshot_live_caches "$after_snapshot"

if ! cmp -s "$before_snapshot" "$after_snapshot"; then
    echo 'FAIL: focused issue #19 tests changed live framework caches' >&2
    diff -u <(tr '\0' '\n' < "$before_snapshot") <(tr '\0' '\n' < "$after_snapshot") >&2 || true
    exit 1
fi

echo 'Focused Sail tests left live framework caches byte-for-byte and metadata-identical.'

if [[ "${PERMISSION_TEST_SKIP_HTTP:-0}" == 1 ]]; then
    echo 'Served HTTP verification explicitly skipped for the CI cache-isolation job.'
    exit 0
fi

[[ -n "${PERMISSION_TEST_BASE_URL:-}" && -f "${PERMISSION_TEST_ADMIN_COOKIE_FILE:-}" ]] || {
    echo 'FAIL: PERMISSION_TEST_BASE_URL and PERMISSION_TEST_ADMIN_COOKIE_FILE are required for served HTTP verification' >&2
    exit 1
}

http_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
    --cookie "$PERMISSION_TEST_ADMIN_COOKIE_FILE" \
    "${PERMISSION_TEST_BASE_URL%/}/admin/tmux-edit")"
[[ "$http_status" == 200 ]] || {
    echo "FAIL: authenticated served /admin/tmux-edit returned HTTP $http_status, expected 200" >&2
    exit 1
}
echo 'Authenticated served /admin/tmux-edit returned HTTP 200 after the focused tests.'
