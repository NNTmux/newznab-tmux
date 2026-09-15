#!/usr/bin/env bash
# Disposable local service integration; no external NNTP or metadata calls.
set -euo pipefail
image=${1:?Supply the locally built production image}
mariadb_image=${SMOKE_MARIADB_IMAGE:-mariadb:11.4.8}
redis_image=${SMOKE_REDIS_IMAGE:-redis:7.4.2-alpine}
subnet=${SMOKE_SUBNET:-172.30.43.0/24}
repository_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)
root=$(mktemp -d /tmp/nntmux-aws-smoke.XXXXXXXX)
project="nntmux-smoke-$$"
compose() { docker compose --project-name "$project" --env-file "$root/compose.env" -f "$repository_root/deploy/cloud/compose.yaml" -f "$root/override.yaml" "$@"; }
cleanup() {
    local status=$?
    trap - EXIT
    if [[ $status -ne 0 ]]; then compose logs --no-color --tail 80 || true; fi
    compose down --timeout 120 || true
    docker run --rm --user 0:0 --entrypoint sh -v "$root/data:/cleanup" "$image" -c 'find /cleanup -mindepth 1 -delete' || true
    rm -rf "$root"
    exit "$status"
}
trap 'cleanup' EXIT
mkdir -p "$root/config" "$root/data"
key=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
cat > "$root/config/application.env" <<CONFIG
APP_KEY=$key
APP_ENV=production
APP_DEBUG=false
APP_URL=https://indexer.example.com
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=nntmux
DB_USERNAME=nntmux
DB_PASSWORD=smoke-only-database-password
DB_ROOTPASSWORD=smoke-only-root-password
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
SEARCH_DRIVER=manticore
MANTICORESEARCH_HOST=manticore
MANTICORESEARCH_PORT=9308
TRUSTED_PROXIES=172.30.42.0/24
COVERS_PATH=/app/storage/covers
PATH_TO_NZBS=/app/storage/nzb
TEMP_UNRAR_PATH=/app/storage/tmp/unrar
TEMP_UNZIP_PATH=/app/storage/tmp/unzip
ADMIN_USER=smoke-admin
ADMIN_PASS=smoke-only-admin-password
ADMIN_EMAIL=admin@example.com
NNTP_SERVER=example.invalid
NNTP_PORT=563
NNTP_SSLENABLED=true
NNTP_USERNAME=smoke-only-user
NNTP_PASSWORD=smoke-only-password
USE_ALTERNATE_NNTP_SERVER=false
MAIL_MAILER=array
CONFIG
docker run --rm --user "$(id -u):$(id -g)" --entrypoint php -v "$root/config:/configuration" "$image" /app/deploy/cloud/configure.php indexer.example.com
cat > "$root/compose.env" <<CONFIG
APP_IMAGE=$image
APPLICATION_HOSTNAME=indexer.example.com
CONFIG_DIRECTORY=$root/config
DATA_ROOT=$root/data
CONFIG
cat > "$root/override.yaml" <<CONFIG
services:
  mariadb:
    image: $mariadb_image
  redis:
    image: $redis_image
networks:
  application:
    internal: true
    ipam:
      config: !override
        - subnet: $subnet
CONFIG
expected_hash=$(sha256sum "$root/config/application.env" | cut -d ' ' -f1)
docker run --rm --user 0:0 --entrypoint sh -v "$root/data:/setup" -v "$root/config/application.env:/configuration.env" "$image" -c 'mkdir -p /setup/storage /setup/install; chown -R 33:33 /setup/storage /setup/install /configuration.env'
compose config --quiet
compose up -d --wait --wait-timeout 300 mariadb redis manticore
compose run --rm --no-deps web php artisan nntmux:deploy-init --no-interaction
if compose run --rm --no-deps web php artisan nntmux:deploy-init --no-interaction; then
    echo 'Reinitialization unexpectedly succeeded.' >&2
    exit 1
fi
compose up -d --wait --wait-timeout 300 web horizon scheduler
compose run --rm --no-deps web php /app/deploy/cloud/health.php
compose exec -T web curl --fail --silent http://localhost/up >/dev/null
compose exec -T scheduler php /app/deploy/cloud/process-health.php schedule:work
compose exec -T horizon php artisan horizon:status --no-interaction
compose run --rm --no-deps web php artisan migrate --force --no-interaction
compose restart web
compose up -d --wait --wait-timeout 300 web
compose exec -T web curl --fail --silent http://localhost/up >/dev/null
actual_hash=$(compose exec -T web sha256sum /app/.env | cut -d ' ' -f1)
[[ "$actual_hash" == "$expected_hash" ]] || { echo 'Initialization or release changed the read-only application configuration.' >&2; exit 1; }
# Verify that the application can write its public cover target and serve it after restart.
compose exec -T web sh -c 'printf "cover-smoke" > /app/storage/covers/smoke.txt'
compose exec -T web curl --fail --silent http://localhost/covers/smoke.txt | grep -qx cover-smoke
echo 'Production container initialization, dependencies, web, Horizon, scheduler, repeat migration, restart, and cover delivery passed.'
