#!/usr/bin/env bash
set -euo pipefail

# Offline checks for install-monitoring.sh: detection, reuse of existing exporters,
# rendered configs and web server edits, all through --dry-run with faked system state.
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
repo_dir=$(cd -- "$script_dir/.." && pwd)
installer="$script_dir/install-monitoring.sh"
sandbox=$(mktemp -d)
trap 'rm -rf -- "$sandbox"' EXIT

fail() {
    echo "Test failure: $1" >&2
    exit 1
}

assert_contains() { [[ $1 == *"$2"* ]] || fail "$3: expected to find [$2]"; }
assert_not_contains() { [[ $1 != *"$2"* ]] || fail "$3: did not expect [$2]"; }

http_key() { printf '%s' "$1" | tr -c 'A-Za-z0-9' '_'; }

# fake_http URL STATUS [BODY]
fake_http() {
    mkdir -p "$fixtures/http"
    printf '%s\n%s\n' "$2" "${3:-}" >"$fixtures/http/$(http_key "$1")"
}

# Fresh app checkout, fake /etc and fixtures for each scenario.
setup() {
    case_dir=$(mktemp -d "$sandbox/case.XXXXXX")
    app="$case_dir/app"
    root="$case_dir/root"
    fixtures="$case_dir/fixtures"
    dry="$case_dir/dry"
    mkdir -p "$app/public" "$root/etc/nginx/sites-available" "$root/etc/nginx/sites-enabled" \
        "$root/etc/php/8.5/fpm/pool.d" "$fixtures" "$dry"
    touch "$app/artisan"
    cp -r "$repo_dir/docker" "$app/docker"
    cat >"$app/.env" <<ENV
APP_URL=https://nntmux.example
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=redis-secret
SEARCH_DRIVER=manticore
MANTICORESEARCH_HOST=127.0.0.1
MANTICORESEARCH_PORT=9308
MONITORING_ENABLED=false
ENV
    printf 'ID=ubuntu\nVERSION_ID="24.04"\n' >"$root/etc/os-release"
    printf '[www]\nuser = www-data\n' >"$root/etc/php/8.5/fpm/pool.d/www.conf"
    cat >"$root/etc/nginx/sites-available/nntmux" <<NGINX
server {
    listen 80;
    server_name nntmux.example;
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl;
    server_name nntmux.example;
    root $app/public;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
NGINX
    ln -s "$root/etc/nginx/sites-available/nntmux" "$root/etc/nginx/sites-enabled/nntmux"
    printf 'nginx.service\nmariadb.service\n' >"$fixtures/units.txt"
    : >"$fixtures/ss.txt"
    : >"$fixtures/packages.txt"
    : >"$fixtures/docker.txt"
    fake_http 'http://127.0.0.1:9308/metrics' 200 'manticore_uptime_seconds 1'
}

run_installer() {
    status=0
    output=$(MONITORING_TEST_FIXTURES="$fixtures" MONITORING_ROOT_PREFIX="$root" MONITORING_DRY_RUN_DIR="$dry" \
        bash "$installer" --app-path="$app" --dry-run "$@" 2>&1) || status=$?
}

# ── Existing node_exporter (e.g. feeding a NAS) is reused, never installed ──
setup
printf 'LISTEN 0 4096 *:9100 *:* users:(("node_exporter",pid=812,fd=3))\n' >"$fixtures/ss.txt"
printf 'node_exporter.service\n' >>"$fixtures/units.txt"
fake_http 'http://127.0.0.1:9100/metrics' 200 'node_exporter_build_info{version="1.8.2"} 1'
run_installer
[[ $status == 0 ]] || fail "Dry run with an existing node_exporter failed ($status): $output"
assert_contains "$output" 'reused, scraping http://127.0.0.1:9100' 'Existing exporter not reported'
assert_contains "$output" '--no-install-recommends prometheus prometheus-pushgateway prometheus-mysqld-exporter prometheus-redis-exporter' 'Unexpected package list'
assert_not_contains "$output" 'prometheus-node-exporter' 'Existing node_exporter would be replaced'
[[ ! -e $dry/etc/default/prometheus-node-exporter ]] || fail 'Existing node_exporter would be reconfigured'
prometheus_yml=$(cat "$dry/etc/prometheus/prometheus.yml")
assert_contains "$prometheus_yml" "job_name: node
    scheme: http
    static_configs:
      - targets: ['127.0.0.1:9100']" 'Node job missing'
assert_contains "$prometheus_yml" "job_name: pushgateway
    honor_labels: true" 'Pushgateway job missing'
assert_contains "$prometheus_yml" "targets: ['127.0.0.1:9308']" 'Manticore job missing'
state=$(cat "$dry/etc/nntmux-monitoring/install.state")
assert_contains "$state" 'packages=grafana prometheus prometheus-mysqld-exporter prometheus-pushgateway prometheus-redis-exporter ' 'State file lists the wrong packages'
grafana_ini=$(cat "$dry/etc/grafana/grafana.ini")
assert_contains "$grafana_ini" 'http_addr = 127.0.0.1' 'Grafana not bound to loopback'
assert_contains "$grafana_ini" 'root_url = https://nntmux.example/grafana/' 'Grafana root_url'
assert_contains "$grafana_ini" 'cookie_secure = true' 'Grafana cookie_secure'
assert_contains "$grafana_ini" 'expect_claims = {"iss": "nntmux", "aud": "grafana"}' 'Grafana JWT claims'
assert_contains "$grafana_ini" 'disable_login_form = true' 'Grafana login form'
assert_contains "$grafana_ini" 'admin_user = nntmux-grafana-admin' 'Grafana built-in admin must not be called admin'
assert_contains "$(cat "$dry/etc/default/prometheus-redis-exporter")" 'REDIS_PASSWORD=redis-secret' 'Redis password'
assert_contains "$(cat "$dry/etc/default/prometheus")" '--web.listen-address=127.0.0.1:9090' 'Prometheus not on loopback'
[[ -f $dry/etc/grafana/dashboards/nntmux/nntmux-host.json ]] || fail 'Dashboards not provisioned'
site="$dry/etc/nginx/sites-available/nntmux"
[[ -f $site ]] || fail "nginx site not rendered: $output"
[[ $(grep -c 'include snippets/nntmux-grafana.conf;' "$site") == 1 ]] || fail 'Include must be added to exactly one server block'
awk '/listen 80;/,/^}/' "$site" | grep -q nntmux-grafana && fail 'Include added to the redirect-only block'
assert_contains "$(cat "$dry/etc/nginx/snippets/nntmux-grafana.conf")" 'proxy_pass http://127.0.0.1:3000;' 'nginx proxy target'
env=$(cat "$dry$app/.env")
assert_contains "$env" 'MONITORING_ENABLED=true' '.env not enabled'
assert_not_contains "$env" 'MONITORING_ENABLED=false' '.env kept the old value'
assert_contains "$env" 'GRAFANA_JWT_PRIVATE_KEY_PATH=/etc/nntmux-monitoring/grafana-jwt.key' '.env key path'
assert_contains "$env" 'MONITORING_PUSHGATEWAY_URL=http://127.0.0.1:9091' '.env pushgateway'

# Re-running against an already edited site changes nothing.
cp "$site" "$root/etc/nginx/sites-available/nntmux"
rm -rf "$dry" && mkdir -p "$dry"
run_installer
[[ $status == 0 ]] || fail "Second dry run failed: $output"
assert_contains "$output" 'already includes the Grafana proxy' 'Include is not idempotent'

# ── Existing exporter behind basic auth ──────────────────────
setup
printf 'LISTEN 0 4096 192.0.2.10:9100 0.0.0.0:* users:(("node_exporter",pid=812,fd=3))\n' >"$fixtures/ss.txt"
fake_http 'http://192.0.2.10:9100/metrics' 401
run_installer
[[ $status == 0 ]] || fail "Auth-protected exporter must not fail the install: $output"
assert_contains "$output" 'needs credentials (HTTP 401)' 'Missing credentials hint'
assert_not_contains "$(cat "$dry/etc/prometheus/prometheus.yml")" 'job_name: node' 'Unreachable exporter must not be scraped'
printf 'nas:s3cret\n' >"$case_dir/node-auth"
rm -rf "$dry" && mkdir -p "$dry"
run_installer --node-exporter-basic-auth-file="$case_dir/node-auth"
assert_contains "$(cat "$dry/etc/prometheus/prometheus.yml")" "basic_auth:
      username: nas
      password_file: /etc/prometheus/node-exporter.password" 'basic_auth not rendered'
assert_contains "$(cat "$dry/etc/prometheus/node-exporter.password")" 's3cret' 'Password file'
assert_contains "$(cat "$dry/etc/prometheus/prometheus.yml")" "targets: ['192.0.2.10:9100']" 'Specific listen IP must be kept'

# ── No exporter: install one on loopback ─────────────────────
setup
run_installer
[[ $status == 0 ]] || fail "Dry run without exporters failed: $output"
assert_contains "$output" 'prometheus prometheus-pushgateway prometheus-node-exporter prometheus-mysqld-exporter' 'node_exporter should be installed'
assert_contains "$(cat "$dry/etc/default/prometheus-node-exporter")" '--web.listen-address=127.0.0.1:9100' 'Installed node_exporter not on loopback'

# ── An exporter this script installed earlier counts as ours ──
setup
mkdir -p "$root/etc/nntmux-monitoring"
printf 'packages=grafana prometheus prometheus-node-exporter prometheus-pushgateway\n' >"$root/etc/nntmux-monitoring/install.state"
printf 'grafana\nprometheus\nprometheus-node-exporter\nprometheus-pushgateway\n' >"$fixtures/packages.txt"
printf 'LISTEN 0 4096 127.0.0.1:9100 0.0.0.0:* users:(("prometheus-node",pid=9,fd=3))\nLISTEN 0 4096 127.0.0.1:3000 0.0.0.0:* users:(("grafana",pid=10,fd=3))\n' >"$fixtures/ss.txt"
run_installer
[[ $status == 0 ]] || fail "Re-run over our own install failed: $output"
assert_contains "$output" 'node_exporter    ours' 'Own exporter misdetected'

# ── Conflicts abort before anything is changed ───────────────
setup
printf 'LISTEN 0 4096 0.0.0.0:3000 0.0.0.0:* users:(("node",pid=77,fd=3))\n' >"$fixtures/ss.txt"
run_installer
[[ $status == 1 ]] || fail "Port conflict should abort (status $status)"
assert_contains "$output" '--grafana-port=PORT' 'Port conflict hint'
[[ ! -e $dry/etc/prometheus/prometheus.yml ]] || fail 'Files rendered despite the conflict'

setup
printf 'prometheus\n' >"$fixtures/packages.txt"
run_installer
[[ $status == 1 ]] || fail 'A foreign Prometheus must not be taken over'
assert_contains "$output" 'Prometheus is already installed but not by this script' 'Foreign Prometheus message'

# ── Apache ───────────────────────────────────────────────────
setup
printf 'apache2.service\n' >"$fixtures/units.txt"
run_installer
[[ $status == 0 ]] || fail "Apache dry run failed: $output"
apache_conf=$(cat "$dry/etc/apache2/conf-available/nntmux-grafana.conf")
assert_contains "$apache_conf" 'ProxyPass http://127.0.0.1:3000/grafana/ upgrade=websocket' 'Apache ProxyPass'
assert_contains "$output" 'would run: a2enconf -q nntmux-grafana' 'Apache conf not enabled'

# ── --detect needs no root and changes nothing ───────────────
setup
printf 'node_exporter.service\n' >>"$fixtures/units.txt"
fake_http 'http://127.0.0.1:9100/metrics' 200 'node_exporter_build_info 1'
status=0
output=$(MONITORING_TEST_FIXTURES="$fixtures" MONITORING_ROOT_PREFIX="$root" bash "$installer" --app-path="$app" --detect 2>&1) || status=$?
[[ $status == 0 ]] || fail "--detect failed: $output"
assert_contains "$output" 'node_exporter    existing' '--detect table'
[[ $(cat "$app/.env") == *'MONITORING_ENABLED=false'* ]] || fail '--detect modified .env'

status=0
output=$(bash "$installer" --app-path="$app" --bogus 2>&1) || status=$?
[[ $status == 1 && $output == *'Unknown option: --bogus'* ]] || fail 'Unknown options must be rejected'

echo 'Monitoring installer checks passed'
