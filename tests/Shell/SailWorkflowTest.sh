#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
fake_bin="$(mktemp -d)"
trap 'rm -rf -- "$fake_bin"' EXIT

cat > "$fake_bin/docker" <<'SH'
#!/usr/bin/env bash
printf '%q ' "$@"
printf '\n'
SH
chmod 0755 "$fake_bin/docker"

run_sail() {
    PATH="$fake_bin:$PATH" SAIL_SKIP_CHECKS=1 APP_SERVICE=laravel.test "$repository_root/sail" "$@" | tail -n 1
}

composer_command="$(make -s -n -C "$repository_root" composer-install)"
[[ "$composer_command" == *'umask 0022'* && "$composer_command" == *'composer install'* ]] || {
    echo "FAIL: composer-install is not wrapped in a scoped build umask: $composer_command" >&2
    exit 1
}

npm_command="$(make -s -n -C "$repository_root" npm-build)"
[[ "$npm_command" == *'umask 0022'* && "$npm_command" == *'npm run build'* ]] || {
    echo "FAIL: npm-build is not wrapped in a scoped build umask: $npm_command" >&2
    exit 1
}
[[ "$npm_command" != *'DEPLOYMENT_OWNER='* ]] || {
    echo "FAIL: npm-build overrides the live permission identity: $npm_command" >&2
    exit 1
}

fix_permissions_command="$(make -s -n -C "$repository_root" fix-permissions)"
[[ "$fix_permissions_command" != *'DEPLOYMENT_OWNER='* && "$fix_permissions_command" != *'APPLICATION_USER='* && "$fix_permissions_command" != *'APPLICATION_GROUP='* ]] || {
    echo "FAIL: fix-permissions overrides the live permission identity: $fix_permissions_command" >&2
    exit 1
}

check_permissions_command="$(make -s -n -C "$repository_root" check-permissions)"
[[ "$check_permissions_command" != *'DEPLOYMENT_OWNER='* && "$check_permissions_command" != *'APPLICATION_USER='* && "$check_permissions_command" != *'APPLICATION_GROUP='* ]] || {
    echo "FAIL: check-permissions overrides the live permission identity: $check_permissions_command" >&2
    exit 1
}

plain_npm_command="$(run_sail npm run test:js)"
[[ "$plain_npm_command" != *'umask 0022'* ]] || {
    echo "FAIL: unrelated Sail npm commands weaken the caller umask: $plain_npm_command" >&2
    exit 1
}

test_command="$(run_sail test --filter=ExampleTest)"
[[ "$test_command" == *'scripts/run-tests-isolated.sh'* && "$test_command" == *'php artisan test'* ]] || {
    echo "FAIL: sail test does not isolate Laravel caches: $test_command" >&2
    exit 1
}

artisan_test_command="$(run_sail artisan test --filter=ExampleTest)"
[[ "$artisan_test_command" == *'scripts/run-tests-isolated.sh'* && "$artisan_test_command" == *'php artisan test'* ]] || {
    echo "FAIL: sail artisan test does not isolate Laravel caches: $artisan_test_command" >&2
    exit 1
}

phpunit_command="$(run_sail phpunit --filter=ExampleTest)"
[[ "$phpunit_command" == *'scripts/run-tests-isolated.sh'* && "$phpunit_command" == *'php vendor/bin/phpunit'* ]] || {
    echo "FAIL: sail phpunit does not isolate Laravel caches: $phpunit_command" >&2
    exit 1
}

php_artisan_test_command="$(run_sail php artisan test --filter=ExampleTest)"
[[ "$php_artisan_test_command" == *'scripts/run-tests-isolated.sh'* && "$php_artisan_test_command" == *'php artisan test'* ]] || {
    echo "FAIL: sail php artisan test does not isolate Laravel caches: $php_artisan_test_command" >&2
    exit 1
}

echo 'Sail workflow regression passed.'
