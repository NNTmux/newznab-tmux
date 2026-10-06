#!/usr/bin/env bash
set -euo pipefail

# Installs Prometheus, the Pushgateway, exporters and Grafana for an existing
# NNTmux install on Ubuntu 22.04/24.04 (nginx or Apache), and wires Grafana into
# the admin pages at /grafana/. Safe to re-run.
#
# Components that are already running and were not installed by this script
# (for example a node_exporter that also feeds another Prometheus) are reused as
# scrape targets and never reconfigured, restarted or removed.
#
# Usually started through `php artisan monitoring:install`, which runs --detect
# and prints the matching command.

readonly STATE_DIR_DEFAULT=/etc/nntmux-monitoring
readonly MARKER='# nntmux-monitoring'
readonly GRAFANA_VERSION_DEFAULT=13.2.3

usage() {
    cat <<'USAGE'
Usage: sudo scripts/install-monitoring.sh [options]

  --app-path=PATH                 NNTmux checkout (default: this script's repository)
  --web-server=auto|nginx|apache  Web server to configure (default: auto)
  --skip-webserver                Do not touch the web server; print the snippet instead
  --web-user=USER                 User PHP-FPM runs as; may read the JWT key (default: detected)
  --nginx-site=FILE               nginx config file holding the NNTmux server block (default: detected)
  --db-admin-user=USER            Create a dedicated, least-privilege MariaDB user for the exporter with this
                                  admin account (default: the exporter uses DB_USERNAME from .env)
  --db-admin-password-file=FILE   File holding the password for --db-admin-user
  --grafana-version=VERSION       Grafana apt version (default: 13.2.3)
  --retention=DURATION            Prometheus retention (default: 30d)
  --grafana-port=PORT             Grafana port on 127.0.0.1 (default: 3000)
  --prometheus-port=PORT          Prometheus port on 127.0.0.1 (default: 9090)
  --pushgateway-port=PORT         Pushgateway port on 127.0.0.1 (default: 9091)

  --node-exporter=MODE            auto|install|existing|skip (default: auto)
  --mysqld-exporter=MODE          auto|install|existing|skip (default: auto)
  --redis-exporter=MODE           auto|install|existing|skip (default: auto)
  --search-exporter=MODE          auto|install|existing|skip (default: auto)
  --node-exporter-url=URL         Scrape URL of an existing node_exporter (also --mysqld/--redis/--search-exporter-url)
  --node-exporter-scheme=SCHEME   http|https for an existing node_exporter
  --node-exporter-basic-auth-file=FILE
                                  File with "user:password" for an existing node_exporter
  --node-exporter-insecure-tls    Skip TLS verification for an existing node_exporter

  --detect                        Only print what is already installed (no root needed)
  --dry-run                       Render every file into a temporary directory and list the actions
  --uninstall [--purge]           Remove what this script installed (--purge also removes packages and data)
  -h, --help                      Show this help
USAGE
}

# ── Output ───────────────────────────────────────────────────
info() { printf '\033[36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[33mwarning:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# ── System access (overridable for tests via MONITORING_TEST_FIXTURES) ──
FIXTURES=${MONITORING_TEST_FIXTURES:-}
# Prefix for every system path that is read, so tests can supply a fake /etc.
ROOT=${MONITORING_ROOT_PREFIX:-}

sys_active_units() {
    if [[ -n $FIXTURES ]]; then cat "$FIXTURES/units.txt" 2>/dev/null || true; return; fi
    systemctl list-units --type=service --state=active --no-legend --plain 2>/dev/null | awk '{print $1}' || true
}

# Lines of `ss -H -ltnp`: State Recv-Q Send-Q Local Peer Process
sys_listeners() {
    if [[ -n $FIXTURES ]]; then cat "$FIXTURES/ss.txt" 2>/dev/null || true; return; fi
    ss -H -ltnp 2>/dev/null || true
}

sys_docker_ps() {
    if [[ -n $FIXTURES ]]; then cat "$FIXTURES/docker.txt" 2>/dev/null || true; return; fi
    command -v docker >/dev/null 2>&1 || return 0
    docker ps --format '{{.Image}} {{.Ports}}' 2>/dev/null || true
}

sys_package_installed() {
    if [[ -n $FIXTURES ]]; then grep -qx "$1" "$FIXTURES/packages.txt" 2>/dev/null; return; fi
    # "hold ok installed" counts too: this script holds grafana.
    dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q ' ok installed$'
}

# Whether apt's history shows this script's own install command for the package.
sys_installed_by_this_script() {
    local history
    if [[ -n $FIXTURES ]]; then
        history=$(cat "$FIXTURES/apt-history.txt" 2>/dev/null || true)
    else
        history=$(zcat -f /var/log/apt/history.log* 2>/dev/null || true)
    fi
    grep '^Commandline: apt-get install -y -q --no-install-recommends ' <<<"$history" | grep -qE "[[:space:]]$1(=[^[:space:]]+)?([[:space:]]|$)"
}

# Prints the HTTP status code (000 on connection/TLS failure); body goes to $2.
sys_http_get() {
    local url=$1 body=$2
    shift 2
    if [[ -n $FIXTURES ]]; then
        local key
        key=$(printf '%s' "$url" | tr -c 'A-Za-z0-9' '_')
        if [[ -f $FIXTURES/http/$key ]]; then
            head -n1 "$FIXTURES/http/$key"
            tail -n +2 "$FIXTURES/http/$key" >"$body"
        else
            printf '000'
            : >"$body"
        fi
        return
    fi
    curl -s -o "$body" -w '%{http_code}' --max-time 5 "$@" "$url" 2>/dev/null || true
}

# ── Options ──────────────────────────────────────────────────
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
APP_PATH=$(cd -- "$script_dir/.." && pwd)
WEB_SERVER=auto
SKIP_WEBSERVER=0
WEB_USER=
NGINX_SITE=
DB_ADMIN_USER=
DB_ADMIN_PASSWORD_FILE=
GRAFANA_VERSION=$GRAFANA_VERSION_DEFAULT
RETENTION=30d
GRAFANA_PORT=3000
PROMETHEUS_PORT=9090
PUSHGATEWAY_PORT=9091
MODE=install
DRY_RUN=0
PURGE=0
STATE_DIR=$STATE_DIR_DEFAULT
declare -A EXPORTER_MODE=([node]=auto [mysqld]=auto [redis]=auto [search]=auto)
declare -A EXPORTER_URL=([node]='' [mysqld]='' [redis]='' [search]='')
NODE_SCHEME=
NODE_BASIC_AUTH_FILE=
NODE_INSECURE_TLS=0

for argument in "$@"; do
    case $argument in
        --app-path=*) APP_PATH=${argument#*=} ;;
        --web-server=*) WEB_SERVER=${argument#*=} ;;
        --skip-webserver) SKIP_WEBSERVER=1 ;;
        --web-user=*) WEB_USER=${argument#*=} ;;
        --nginx-site=*) NGINX_SITE=${argument#*=} ;;
        --db-admin-user=*) DB_ADMIN_USER=${argument#*=} ;;
        --db-admin-password-file=*) DB_ADMIN_PASSWORD_FILE=${argument#*=} ;;
        --grafana-version=*) GRAFANA_VERSION=${argument#*=} ;;
        --retention=*) RETENTION=${argument#*=} ;;
        --grafana-port=*) GRAFANA_PORT=${argument#*=} ;;
        --prometheus-port=*) PROMETHEUS_PORT=${argument#*=} ;;
        --pushgateway-port=*) PUSHGATEWAY_PORT=${argument#*=} ;;
        --node-exporter=* | --mysqld-exporter=* | --redis-exporter=* | --search-exporter=*)
            name=${argument%%-exporter=*}
            EXPORTER_MODE[${name#--}]=${argument#*=}
            ;;
        --node-exporter-url=* | --mysqld-exporter-url=* | --redis-exporter-url=* | --search-exporter-url=*)
            name=${argument%%-exporter-url=*}
            EXPORTER_URL[${name#--}]=${argument#*=}
            ;;
        --node-exporter-scheme=*) NODE_SCHEME=${argument#*=} ;;
        --node-exporter-basic-auth-file=*) NODE_BASIC_AUTH_FILE=${argument#*=} ;;
        --node-exporter-insecure-tls) NODE_INSECURE_TLS=1 ;;
        --detect) MODE=detect ;;
        --dry-run) DRY_RUN=1 ;;
        --uninstall) MODE=uninstall ;;
        --purge) PURGE=1 ;;
        -h | --help) usage; exit 0 ;;
        *) usage >&2; die "Unknown option: $argument" ;;
    esac
done

for name in "${!EXPORTER_MODE[@]}"; do
    [[ ${EXPORTER_MODE[$name]} =~ ^(auto|install|existing|skip)$ ]] || die "--$name-exporter must be auto, install, existing or skip."
done
[[ $WEB_SERVER =~ ^(auto|nginx|apache)$ ]] || die '--web-server must be auto, nginx or apache.'
[[ -z $NODE_SCHEME || $NODE_SCHEME =~ ^https?$ ]] || die '--node-exporter-scheme must be http or https.'
for port in "$GRAFANA_PORT" "$PROMETHEUS_PORT" "$PUSHGATEWAY_PORT"; do
    [[ $port =~ ^[0-9]+$ ]] || die "Invalid port: $port"
done
[[ -f $APP_PATH/artisan ]] || die "No NNTmux checkout at $APP_PATH (artisan not found). Use --app-path."
APP_PATH=$(cd -- "$APP_PATH" && pwd)
ENV_FILE=$APP_PATH/.env
[[ -f $ENV_FILE ]] || die "No .env at $ENV_FILE; finish the NNTmux install first."

WORK_DIR=$(mktemp -d)
cleanup() {
    rm -rf -- "$WORK_DIR"
    # Never leave package service starts blocked if the script dies mid-install.
    if [[ -e /usr/sbin/policy-rc.d.nntmux-monitoring ]]; then
        rm -f /usr/sbin/policy-rc.d /usr/sbin/policy-rc.d.nntmux-monitoring
    fi
}
trap cleanup EXIT
DRY_DIR=
if ((DRY_RUN)); then
    DRY_DIR=${MONITORING_DRY_RUN_DIR:-$(mktemp -d /tmp/nntmux-monitoring-dry-run.XXXXXX)}
fi

# ── .env ─────────────────────────────────────────────────────
# Reads a key from .env the way Laravel (phpdotenv) does: double quotes allow \" and \\
# escapes, single quotes are literal, and unquoted values end at #.
env_value() {
    local line value
    # phpdotenv also accepts "export KEY=…" and spaces around "=".
    line=$(grep -E "^[[:space:]]*(export[[:space:]]+)?$1[[:space:]]*=" "$ENV_FILE" | tail -n1 || true)
    value=${line#*=}
    value=${value#"${value%%[![:space:]]*}"}
    if [[ $value == \"* ]]; then
        value=${value#\"}
        value=${value%\"*}
        value=${value//\\\"/\"}
        value=${value//\\\\/\\}
    elif [[ $value == \'* ]]; then
        value=${value#\'}
        value=${value%\'*}
    else
        value=${value%%#*}
        value=${value%"${value##*[![:space:]]}"}
    fi
    printf '%s' "$value"
}

APP_URL=$(env_value APP_URL)
APP_URL=${APP_URL%/}
[[ -n $APP_URL ]] || APP_URL=http://localhost
DB_HOST=$(env_value DB_HOST)
DB_PORT=$(env_value DB_PORT)
DB_USERNAME=$(env_value DB_USERNAME)
DB_PASSWORD=$(env_value DB_PASSWORD)
DB_SOCKET=$(env_value DB_SOCKET)
REDIS_HOST=$(env_value REDIS_HOST)
REDIS_PORT=$(env_value REDIS_PORT)
REDIS_PASSWORD=$(env_value REDIS_PASSWORD)
[[ $REDIS_PASSWORD == null ]] && REDIS_PASSWORD=
SEARCH_DRIVER=$(env_value SEARCH_DRIVER)
SEARCH_DRIVER=${SEARCH_DRIVER:-manticore}
MANTICORE_HOST=$(env_value MANTICORESEARCH_HOST)
MANTICORE_PORT=$(env_value MANTICORESEARCH_PORT)
ES_HOST=$(env_value ELASTICSEARCH_HOST)
ES_PORT=$(env_value ELASTICSEARCH_PORT)
ES_SCHEME=$(env_value ELASTICSEARCH_SCHEME)
ES_USER=$(env_value ELASTICSEARCH_USER)
ES_PASS=$(env_value ELASTICSEARCH_PASS)
loopback_host() { [[ -z $1 || $1 == localhost || $1 == 127.0.0.1 || $1 == ::1 ]]; }

# ── State: what this script installed ────────────────────────
STATE_FILE=$ROOT$STATE_DIR/install.state

state_get() { grep -E "^$1=" "$STATE_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- || true; }

ADOPTED_PACKAGES=''
installed_by_us() { [[ " $(state_get packages) $ADOPTED_PACKAGES " == *" $1 "* ]]; }

service_for_package() {
    case $1 in
        grafana) echo grafana-server ;;
        *) echo "$1" ;;
    esac
}

# A run that died before writing install.state (older versions only wrote it at the
# end) leaves its packages installed plus our marker in /etc/default/prometheus.
# Adopt a package when it never started, or when apt's history shows this script's
# own install command for it. A running service installed some other way, such as
# a node_exporter that also feeds a NAS, is never claimed.
adopt_partial_install() {
    [[ -f $STATE_FILE ]] && return
    grep -q 'Managed by NNTmux scripts/install-monitoring.sh' "$ROOT/etc/default/prometheus" 2>/dev/null || return 0
    local package active
    active=$(sys_active_units)
    for package in grafana prometheus prometheus-pushgateway prometheus-node-exporter prometheus-mysqld-exporter prometheus-redis-exporter prometheus-elasticsearch-exporter; do
        sys_package_installed "$package" || continue
        if ! grep -qx "$(service_for_package "$package").service" <<<"$active" || sys_installed_by_this_script "$package"; then
            ADOPTED_PACKAGES+=" $package"
        fi
    done
    [[ -n $ADOPTED_PACKAGES ]] && info "Resuming an interrupted install; adopting:$ADOPTED_PACKAGES"
    return 0
}

# ── Detection ────────────────────────────────────────────────
# Per exporter: process name pattern, default port, Ubuntu package, metric proving identity.
# Process names as `ss` prints them: the kernel truncates them to 15 characters.
declare -A EXPORTER_PROCESS=([node]='node_exporter|prometheus-node' [mysqld]='mysqld_exporter|prometheus-mysq' [redis]='redis_exporter|prometheus-redi' [search]='elasticsearch_e|prometheus-elas')
declare -A EXPORTER_PORT=([node]=9100 [mysqld]=9104 [redis]=9121 [search]=9114)
declare -A EXPORTER_PACKAGE=([node]=prometheus-node-exporter [mysqld]=prometheus-mysqld-exporter [redis]=prometheus-redis-exporter [search]=prometheus-elasticsearch-exporter)
declare -A EXPORTER_METRIC=([node]=node_exporter_build_info [mysqld]=mysql_up [redis]=redis_up [search]=elasticsearch_)
declare -A EXPORTER_DOCKER=([node]=node-exporter [mysqld]=mysqld-exporter [redis]=redis_exporter [search]=elasticsearch-exporter)
declare -A DETECTED=()   # existing | ours | absent | skip
declare -A TARGET=()     # host:port Prometheus scrapes
declare -A SCHEME=()
declare -A NOTE=()

# Echo host:port of a listener whose process matches $1 (empty if none).
listener_for_process() {
    local line local_address
    line=$(sys_listeners | grep -E "\"($1)\"" | head -n1 || true)
    [[ -n $line ]] || return 0
    local_address=$(awk '{print $4}' <<<"$line")
    printf '%s' "$local_address"
}

port_in_use() {
    sys_listeners | awk '{print $4}' | grep -Eq "[:.]$1\$"
}

port_owner() {
    sys_listeners | awk -v p=":$1" 'index($4, p) && substr($4, length($4) - length(p) + 1) == p {print $6}' | head -n1
}

# Turn a listen address into something Prometheus on this host can reach.
scrape_address() {
    local address=$1 host port
    port=${address##*:}
    host=${address%:*}
    host=${host#[}
    host=${host%]}
    case $host in
        '*' | 0.0.0.0 | :: | '') host=127.0.0.1 ;;
    esac
    [[ $host == *:* ]] && host="[$host]"
    printf '%s:%s' "$host" "$port"
}

probe_exporter() {
    local name=$1 address=$2 scheme code
    local -a curl_options=()
    if [[ $name == node ]]; then
        ((NODE_INSECURE_TLS)) && curl_options+=(-k)
        [[ -n $NODE_BASIC_AUTH_FILE ]] && curl_options+=(-u "$(<"$NODE_BASIC_AUTH_FILE")")
    fi
    for scheme in ${SCHEME[$name]:-http https}; do
        code=$(sys_http_get "$scheme://$address/metrics" "$WORK_DIR/probe" "${curl_options[@]}")
        if [[ $code == 200 ]] && grep -q "^${EXPORTER_METRIC[$name]}" "$WORK_DIR/probe"; then
            SCHEME[$name]=$scheme
            return 0
        fi
        if [[ $code == 401 || $code == 403 ]]; then
            SCHEME[$name]=$scheme
            NOTE[$name]="needs credentials (HTTP $code)"
            return 2
        fi
    done
    return 1
}

detect_exporter() {
    local name=$1 mode=${EXPORTER_MODE[$1]} address='' found=0 result=0
    [[ $name == node && -n $NODE_SCHEME ]] && SCHEME[node]=$NODE_SCHEME

    if [[ $mode == skip ]]; then
        DETECTED[$name]=skip
        return
    fi
    if [[ $name == search && $SEARCH_DRIVER != elasticsearch ]]; then
        DETECTED[$name]=skip
        NOTE[$name]="SEARCH_DRIVER=$SEARCH_DRIVER (Manticore serves its own /metrics)"
        return
    fi
    if installed_by_us "${EXPORTER_PACKAGE[$name]}"; then
        DETECTED[$name]=ours
        TARGET[$name]=127.0.0.1:${EXPORTER_PORT[$name]}
        SCHEME[$name]=http
        return
    fi

    if [[ -n ${EXPORTER_URL[$name]} ]]; then
        local url=${EXPORTER_URL[$name]}
        [[ $url == *://* ]] && SCHEME[$name]=${url%%://*}
        url=${url#*://}
        address=${url%%/*}
        found=1
    else
        address=$(listener_for_process "${EXPORTER_PROCESS[$name]}")
        [[ -n $address ]] && found=1
        if ((!found)) && sys_active_units | grep -Eq "^(${EXPORTER_PROCESS[$name]})[^ ]*\.service$"; then
            found=1
        fi
        if ((!found)) && sys_docker_ps | grep -q "${EXPORTER_DOCKER[$name]}"; then
            found=1
            address=$(sys_docker_ps | grep "${EXPORTER_DOCKER[$name]}" | grep -oE '[0-9.:]+->[0-9]+' | head -n1 | cut -d- -f1 || true)
        fi
        if ((!found)) && sys_package_installed "${EXPORTER_PACKAGE[$name]}"; then
            found=1
        fi
        [[ -z $address ]] && address=127.0.0.1:${EXPORTER_PORT[$name]}
    fi

    if ((!found)); then
        [[ $mode == existing ]] && die "--$name-exporter=existing, but no running $name exporter was found. Pass --$name-exporter-url."
        DETECTED[$name]=absent
        return
    fi
    [[ $mode == install ]] && die "A $name exporter is already running (not installed by this script). Use --$name-exporter=existing or skip."

    DETECTED[$name]=existing
    TARGET[$name]=$(scrape_address "$address")
    probe_exporter "$name" "${TARGET[$name]}" || result=$?
    case $result in
        0) NOTE[$name]="reused, scraping ${SCHEME[$name]}://${TARGET[$name]}" ;;
        2) [[ $name == node && -n $NODE_BASIC_AUTH_FILE ]] || NOTE[$name]="${NOTE[$name]}; pass --node-exporter-basic-auth-file (host metrics skipped until then)" ;;
        *) NOTE[$name]="found, but /metrics did not answer at ${TARGET[$name]}; pass --$name-exporter-url (skipped until then)" ;;
    esac
    if ((result != 0)) && ! [[ $result == 2 && $name == node && -n $NODE_BASIC_AUTH_FILE ]]; then
        TARGET[$name]=
    fi
}

check_core_component() {
    local label=$1 package=$2 port=$3 owner
    if installed_by_us "$package"; then
        return
    fi
    if sys_package_installed "$package"; then
        die "$label is already installed but not by this script. It is left alone rather than overwriting its configuration; remove it or add NNTmux's jobs to it manually."
    fi
    if port_in_use "$port"; then
        owner=$(port_owner "$port")
        die "Port $port (for $label) is already used${owner:+ by $owner}. Pass --${label,,}-port=PORT."
    fi
}

detect_web_server() {
    [[ $WEB_SERVER != auto ]] && return
    if sys_active_units | grep -qx 'nginx.service'; then
        WEB_SERVER=nginx
    elif sys_active_units | grep -qx 'apache2.service'; then
        WEB_SERVER=apache
    else
        WEB_SERVER=none
    fi
}

detect_web_user() {
    [[ -n $WEB_USER ]] && return
    local users
    users=$(grep -hE '^\s*user\s*=' "$ROOT"/etc/php/*/fpm/pool.d/*.conf 2>/dev/null | sed -E 's/^\s*user\s*=\s*//; s/\s+$//' | sort -u || true)
    if [[ -n $users && $(wc -l <<<"$users") -eq 1 ]]; then
        WEB_USER=$users
    else
        WEB_USER=www-data
    fi
}

print_detection() {
    local name label
    printf '\n%-16s %-9s %s\n' COMPONENT STATUS DETAILS
    for name in node mysqld redis search; do
        label="${name}_exporter"
        [[ $name == search ]] && label=search_exporter
        printf '%-16s %-9s %s\n' "$label" "${DETECTED[$name]}" "${NOTE[$name]:-${TARGET[$name]:-}}"
    done
    printf '%-16s %-9s %s\n' web_server "$WEB_SERVER" "PHP-FPM user: $WEB_USER"
    echo
}

run_detection() {
    local name
    for name in node mysqld redis search; do
        detect_exporter "$name"
    done
    detect_web_server
    detect_web_user
}

# ── File writing (dry-run aware) ─────────────────────────────
# install_file SOURCE DEST MODE OWNER:GROUP
install_file() {
    local source=$1 dest=$2 mode=$3 owner=$4
    if ((DRY_RUN)); then
        mkdir -p -- "$DRY_DIR$(dirname -- "$dest")"
        cp -- "$source" "$DRY_DIR$dest"
        echo "  would write $dest ($mode $owner)"
        return
    fi
    install -D -m "$mode" -o "${owner%%:*}" -g "${owner##*:}" -- "$source" "$dest"
}

run() {
    if ((DRY_RUN)); then
        echo "  would run: $*"
        return 0
    fi
    "$@"
}

# ── Renderers ────────────────────────────────────────────────
render_prometheus_config() {
    local out=$1
    {
        cat <<YAML
# Managed by NNTmux scripts/install-monitoring.sh; re-run it instead of editing.
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: prometheus
    static_configs:
      - targets: ['127.0.0.1:$PROMETHEUS_PORT']

  # NNTmux application metrics pushed every minute by \`monitoring:export-metrics\`.
  - job_name: pushgateway
    honor_labels: true
    static_configs:
      - targets: ['127.0.0.1:$PUSHGATEWAY_PORT']
YAML
        if [[ -n ${TARGET[node]:-} ]]; then
            printf '\n  - job_name: node\n    scheme: %s\n' "${SCHEME[node]:-http}"
            if [[ -n $NODE_BASIC_AUTH_FILE && ${DETECTED[node]} == existing ]]; then
                printf '    basic_auth:\n      username: %s\n      password_file: /etc/prometheus/node-exporter.password\n' "$(cut -d: -f1 "$NODE_BASIC_AUTH_FILE")"
            fi
            if ((NODE_INSECURE_TLS)) && [[ ${DETECTED[node]} == existing ]]; then
                printf '    tls_config:\n      insecure_skip_verify: true\n'
            fi
            printf "    static_configs:\n      - targets: ['%s']\n" "${TARGET[node]}"
        fi
        if [[ -n ${TARGET[mysqld]:-} ]]; then
            printf "\n  - job_name: mariadb\n    scheme: %s\n    static_configs:\n      - targets: ['%s']\n" "${SCHEME[mysqld]:-http}" "${TARGET[mysqld]}"
        fi
        if [[ -n ${TARGET[redis]:-} ]]; then
            printf "\n  - job_name: redis\n    scheme: %s\n    static_configs:\n      - targets: ['%s']\n" "${SCHEME[redis]:-http}" "${TARGET[redis]}"
        fi
        if [[ -n ${TARGET[search]:-} ]]; then
            printf "\n  - job_name: elasticsearch\n    scheme: %s\n    static_configs:\n      - targets: ['%s']\n" "${SCHEME[search]:-http}" "${TARGET[search]}"
        fi
        if [[ -n ${MANTICORE_TARGET:-} ]]; then
            printf "\n  - job_name: manticore\n    static_configs:\n      - targets: ['%s']\n" "$MANTICORE_TARGET"
        fi
    } >"$out"
}

render_grafana_ini() {
    local out=$1 admin_password=$2 cookie_secure=false
    [[ $APP_URL == https://* ]] && cookie_secure=true
    cat >"$out" <<INI
; Managed by NNTmux scripts/install-monitoring.sh; re-run it instead of editing.
; Anything not set here falls back to /usr/share/grafana/conf/defaults.ini.

[server]
http_addr = 127.0.0.1
http_port = $GRAFANA_PORT
root_url = $APP_URL/grafana/
serve_from_sub_path = true

[security]
; Not "admin": NNTmux usernames become Grafana logins, and an NNTmux admin called
; "admin" would otherwise collide with Grafana's built-in (unusable) admin account.
admin_user = nntmux-grafana-admin
admin_password = $admin_password
allow_embedding = true
cookie_samesite = lax
cookie_secure = $cookie_secure

[users]
allow_sign_up = false

[auth]
; Admins are signed in by NNTmux with a short-lived JWT (see [auth.jwt]).
disable_login_form = true

[auth.basic]
enabled = false

[auth.anonymous]
enabled = false

[auth.jwt]
enabled = true
url_login = true
header_name = X-JWT-Assertion
key_file = /etc/grafana/nntmux-jwt.pub
username_claim = sub
email_claim = email
expect_claims = {"iss": "nntmux", "aud": "grafana"}
auto_sign_up = true
role_attribute_path = role
role_attribute_strict = true

[dashboards]
default_home_dashboard_path = /etc/grafana/dashboards/nntmux/nntmux-host.json

[live]
; Dashboards refresh by polling; Live's websocket only adds reconnect noise in the embeds.
max_connections = 0

[analytics]
reporting_enabled = false
check_for_updates = false
check_for_plugin_updates = false

[news]
news_feed_enabled = false
INI
}

render_nginx_snippets() {
    local map_out=$1 snippet_out=$2
    cat >"$map_out" <<NGINX
$MARKER: websocket upgrade for Grafana Live behind /grafana/
map \$http_upgrade \$nntmux_grafana_connection {
    default upgrade;
    '' close;
}
NGINX
    cat >"$snippet_out" <<NGINX
$MARKER: Grafana reverse proxy. Grafana authenticates the NNTmux JWT itself.
location ^~ /grafana/ {
    # Declaring add_header here stops nginx inheriting the site's own add_header
    # lines; a site-wide Content-Security-Policy would block Grafana's inline
    # boot scripts and leave it on its loading screen.
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
NGINX_HEADERS_MORE
    proxy_pass http://127.0.0.1:$GRAFANA_PORT;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Upgrade \$http_upgrade;
    proxy_set_header Connection \$nntmux_grafana_connection;
}
NGINX
}

# headers-more (more_set_headers) is not reset by add_header, so clear its CSP explicitly.
nginx_uses_headers_more() {
    if [[ -n $FIXTURES ]]; then
        [[ -f $FIXTURES/nginx-headers-more ]]
        return
    fi
    nginx -T 2>/dev/null | grep -Eq '^[[:space:]]*more_set_headers[[:space:]]'
}

finish_nginx_snippet() {
    local snippet=$1 line=''
    if nginx_uses_headers_more; then
        line='    more_clear_headers Content-Security-Policy;'
    fi
    awk -v line="$line" '$0 == "NGINX_HEADERS_MORE" { if (line != "") print line; next } { print }' "$snippet" >"$snippet.tmp"
    mv "$snippet.tmp" "$snippet"
}

render_apache_conf() {
    cat >"$1" <<APACHE
$MARKER: Grafana reverse proxy. Grafana authenticates the NNTmux JWT itself.
<Location /grafana/>
    ProxyPass http://127.0.0.1:$GRAFANA_PORT/grafana/ upgrade=websocket
    ProxyPassReverse http://127.0.0.1:$GRAFANA_PORT/grafana/
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
    # A site-wide Content-Security-Policy would block Grafana's inline boot scripts.
    Header always unset Content-Security-Policy
    Header unset Content-Security-Policy
    Header always set X-Frame-Options SAMEORIGIN
</Location>
APACHE
}

# Insert the include into every server block whose root is the NNTmux public dir.
# Prints the new file to stdout; idempotent when the include is already present.
# $2: newline-separated root values, exactly as written in that file, that resolve to the app.
nginx_add_include() {
    local site=$1 roots=$2
    awk -v roots="$roots" -v include_line="    include snippets/nntmux-grafana.conf; $MARKER" '
        BEGIN { root_count = split(roots, wanted, "\n") }
        function root_matches(line,   value, r) {
            if (line !~ /^[[:space:]]*root[[:space:]]/) return 0
            value = line
            sub(/^[[:space:]]*root[[:space:]]+/, "", value)
            sub(/[[:space:]]*;.*$/, "", value)
            for (r = 1; r <= root_count; r++) if (value == wanted[r]) return 1
            return 0
        }
        function flush(   i, has_root, has_include) {
            has_root = 0; has_include = 0
            for (i = 1; i <= n; i++) {
                if (root_matches(block[i])) has_root = i
                if (index(block[i], "nntmux-grafana.conf")) has_include = 1
            }
            for (i = 1; i <= n; i++) {
                print block[i]
                if (i == has_root && !has_include) print include_line
            }
            n = 0
        }
        {
            line = $0
            if (!inserver && line ~ /^[[:space:]]*server[[:space:]]*\{/) { inserver = 1; depth = 0 }
            if (inserver) {
                block[++n] = line
                tmp = line; opens = gsub(/\{/, "", tmp); tmp = line; closes = gsub(/\}/, "", tmp)
                depth += opens - closes
                if (depth <= 0) { inserver = 0; flush() }
                next
            }
            print line
        }
        END { if (n) flush() }
    ' "$site"
}

nginx_remove_include() {
    grep -vF "nntmux-grafana.conf; $MARKER" "$1"
}

# Config files nginx actually loads (nginx -T), else the usual Ubuntu locations.
nginx_config_files() {
    if [[ -n $NGINX_SITE ]]; then
        printf '%s\n' "$NGINX_SITE"
        return
    fi
    local files=''
    if [[ -z $FIXTURES ]] && command -v nginx >/dev/null 2>&1; then
        files=$(nginx -T 2>/dev/null | sed -n 's/^# configuration file \(.*\):$/\1/p' || true)
    fi
    if [[ -z $files ]]; then
        files=$(ls -1 "$ROOT"/etc/nginx/nginx.conf "$ROOT"/etc/nginx/conf.d/*.conf "$ROOT"/etc/nginx/sites-enabled/* 2>/dev/null || true)
    fi
    printf '%s\n' "$files"
}

# Normalize a root value: drop quotes and trailing slashes, resolve symlinks.
normalize_root() {
    local value=${1//\"/}
    value=${value//\'/}
    while [[ $value == */ && $value != / ]]; do value=${value%/}; done
    realpath -m -- "$value" 2>/dev/null || printf '%s' "$value"
}

# Prints "file<TAB>root-as-written" for every root directive that resolves to the app's public dir.
nginx_sites_for_app() {
    local app_public file value
    app_public=$(normalize_root "$APP_PATH/public")
    while IFS= read -r file; do
        [[ -n $file && -f $file ]] || continue
        while IFS= read -r value; do
            if [[ $(normalize_root "$value") == "$app_public" ]]; then
                printf '%s\t%s\n' "$(readlink -f -- "$file")" "$value"
            fi
        done < <(sed -nE 's/^[[:space:]]*root[[:space:]]+([^;]+);.*/\1/p' "$file" | sed -E 's/[[:space:]]+$//')
    done < <(nginx_config_files)
}

nginx_roots_seen() {
    local file
    while IFS= read -r file; do
        [[ -n $file && -f $file ]] || continue
        grep -HnE '^[[:space:]]*root[[:space:]]' "$file" 2>/dev/null | sed "s|^|    |" || true
    done < <(nginx_config_files)
}

# ── Install steps ────────────────────────────────────────────
APT_UPDATED=0
apt_install() {
    ((APT_UPDATED)) || { run apt-get update -q; APT_UPDATED=1; }
    run env DEBIAN_FRONTEND=noninteractive apt-get install -y -q --no-install-recommends "$@"
}

# Keep packages from starting with their default (0.0.0.0) configuration before
# this script has rewritten it.
block_service_starts() {
    ((DRY_RUN)) && return
    if [[ ! -e /usr/sbin/policy-rc.d ]]; then
        printf '#!/bin/sh\nexit 101\n' >/usr/sbin/policy-rc.d
        chmod 755 /usr/sbin/policy-rc.d
        touch /usr/sbin/policy-rc.d.nntmux-monitoring
    fi
}

unblock_service_starts() {
    ((DRY_RUN)) && return
    if [[ -e /usr/sbin/policy-rc.d.nntmux-monitoring ]]; then
        rm -f /usr/sbin/policy-rc.d /usr/sbin/policy-rc.d.nntmux-monitoring
    fi
}

install_packages() {
    local -a packages=(prometheus prometheus-pushgateway)
    local name
    for name in node mysqld redis search; do
        [[ ${DETECTED[$name]} == absent || ${DETECTED[$name]} == ours ]] && packages+=("${EXPORTER_PACKAGE[$name]}")
    done

    # Record what is about to be installed before apt runs, so a run that fails
    # halfway can be re-run (or uninstalled) instead of mistaking its own
    # packages for a foreign install.
    INSTALLED_PACKAGES=$(printf '%s\n' "$(state_get packages)" "$ADOPTED_PACKAGES" "${packages[@]}" grafana | tr ' ' '\n' | sed '/^$/d' | sort -u | tr '\n' ' ')
    save_state "$INSTALLED_PACKAGES" "$(state_get web_server)" "$(state_get nginx_site)"

    info "Installing ${packages[*]}"
    block_service_starts
    apt_install "${packages[@]}"

    if [[ ! -f $ROOT/etc/apt/sources.list.d/grafana.list ]]; then
        info "Adding the Grafana apt repository"
        apt_install apt-transport-https gnupg ca-certificates curl
        run install -d -m 0755 /etc/apt/keyrings
        run sh -c 'curl -fsSL https://apt.grafana.com/gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/grafana.gpg'
        printf 'deb [signed-by=/etc/apt/keyrings/grafana.gpg] https://apt.grafana.com stable main\n' >"$WORK_DIR/grafana.list"
        install_file "$WORK_DIR/grafana.list" /etc/apt/sources.list.d/grafana.list 0644 root:root
        APT_UPDATED=0
    fi
    info "Installing Grafana $GRAFANA_VERSION"
    run apt-mark unhold grafana >/dev/null 2>&1 || true
    apt_install "grafana=$GRAFANA_VERSION"
    run apt-mark hold grafana
    unblock_service_starts

}

write_defaults_file() {
    local package=$1 content=$2 mode=${3:-0644}
    printf '# Managed by NNTmux scripts/install-monitoring.sh\n%s\n' "$content" >"$WORK_DIR/$package.default"
    install_file "$WORK_DIR/$package.default" "/etc/default/$package" "$mode" root:prometheus
}

configure_exporters() {
    info "Binding Prometheus, the Pushgateway and installed exporters to 127.0.0.1"
    write_defaults_file prometheus "ARGS=\"--web.listen-address=127.0.0.1:$PROMETHEUS_PORT --storage.tsdb.retention.time=$RETENTION\""
    write_defaults_file prometheus-pushgateway "ARGS=\"--web.listen-address=127.0.0.1:$PUSHGATEWAY_PORT\""

    if [[ ${DETECTED[node]} == absent || ${DETECTED[node]} == ours ]]; then
        write_defaults_file prometheus-node-exporter "ARGS=\"--web.listen-address=127.0.0.1:9100\""
        TARGET[node]=127.0.0.1:9100
        SCHEME[node]=http
    fi

    if [[ ${DETECTED[mysqld]} == absent || ${DETECTED[mysqld]} == ours ]]; then
        configure_mysqld_exporter
        TARGET[mysqld]=127.0.0.1:9104
    fi

    if [[ ${DETECTED[redis]} == absent || ${DETECTED[redis]} == ours ]]; then
        local redis_address="redis://${REDIS_HOST:-127.0.0.1}:${REDIS_PORT:-6379}"
        local redis_env="ARGS=\"--web.listen-address=127.0.0.1:9121 --redis.addr=$redis_address\""
        [[ -n $REDIS_PASSWORD ]] && redis_env+=$'\n'"REDIS_PASSWORD=$REDIS_PASSWORD"
        write_defaults_file prometheus-redis-exporter "$redis_env" 0640
        TARGET[redis]=127.0.0.1:9121
    fi

    if [[ ${DETECTED[search]} == absent || ${DETECTED[search]} == ours ]]; then
        local es_credentials=''
        if [[ -n $ES_USER ]]; then
            # The Ubuntu builds only take the URI as a flag, so credentials end up in the process list.
            es_credentials="$ES_USER:$ES_PASS@"
            warn "ELASTICSEARCH_USER is set: its credentials are passed to elasticsearch_exporter via --es.uri and are visible to local users in the process list."
        fi
        write_defaults_file prometheus-elasticsearch-exporter "ARGS=\"--web.listen-address=127.0.0.1:9114 --es.uri=${ES_SCHEME:-http}://$es_credentials${ES_HOST:-127.0.0.1}:${ES_PORT:-9200}\"" 0640
        TARGET[search]=127.0.0.1:9114
    fi

    if [[ -n $NODE_BASIC_AUTH_FILE && ${DETECTED[node]} == existing ]]; then
        cut -d: -f2- "$NODE_BASIC_AUTH_FILE" >"$WORK_DIR/node-exporter.password"
        install_file "$WORK_DIR/node-exporter.password" /etc/prometheus/node-exporter.password 0640 root:prometheus
    fi
}

# Writes a private MariaDB option file; quoting keeps passwords with special characters intact.
write_db_admin_cnf() {
    local file=$1 user=$2 password=$3
    password=${password//\\/\\\\}
    password=${password//\"/\\\"}
    (umask 077 && printf '[client]\nuser="%s"\npassword="%s"\n' "$user" "$password" >"$file")
}

# Runs SQL as the first MariaDB account that can: --db-admin-user, root via the unix
# socket (Ubuntu default), /etc/mysql/debian.cnf, then DB_USERNAME from .env.
# Prints the working client options on success.
mariadb_admin() {
    local sql=$1 candidate
    local -a candidates=()
    if [[ -n $DB_ADMIN_USER ]]; then
        [[ -n $DB_ADMIN_PASSWORD_FILE && -r $DB_ADMIN_PASSWORD_FILE ]] || die "--db-admin-user needs a readable --db-admin-password-file."
        write_db_admin_cnf "$WORK_DIR/db-admin-flag.cnf" "$DB_ADMIN_USER" "$(head -n1 "$DB_ADMIN_PASSWORD_FILE")"
        candidates+=("--defaults-extra-file=$WORK_DIR/db-admin-flag.cnf")
    else
        candidates+=("")
        [[ -r /etc/mysql/debian.cnf ]] && candidates+=("--defaults-file=/etc/mysql/debian.cnf")
        if [[ -n $DB_USERNAME ]]; then
            write_db_admin_cnf "$WORK_DIR/db-admin-env.cnf" "$DB_USERNAME" "$DB_PASSWORD"
            candidates+=("--defaults-extra-file=$WORK_DIR/db-admin-env.cnf")
        fi
    fi
    for candidate in "${candidates[@]}"; do
        # shellcheck disable=SC2086 # empty candidate = plain socket login
        if mariadb $candidate -NBe "$sql" >/dev/null 2>"$WORK_DIR/mariadb-error"; then
            printf '%s' "$candidate"
            return 0
        fi
    done
    return 1
}

# Quote a value for mysqld_exporter's my.cnf parser (go-ini), which treats # and ;
# as comments: backticks keep the value raw, """ is the fallback.
exporter_cnf_quote() {
    if [[ $1 != *'`'* ]]; then
        # shellcheck disable=SC2016 # literal backticks are go-ini's raw-value quotes
        printf '`%s`' "$1"
    elif [[ $1 != *'"""'* ]]; then
        printf '"""%s"""' "$1"
    else
        die "DB_PASSWORD contains both a backtick and \"\"\"; use --db-admin-user to create a dedicated exporter user instead."
    fi
}

# Default: the exporter logs in as the NNTmux database user from .env, the same way
# Laravel does. Without PROCESS/replication rights only the global status and
# variables collectors run, which is all the dashboards use.
configure_mysqld_exporter_with_app_user() {
    local connection
    if [[ -n $DB_SOCKET ]]; then
        connection="socket=$DB_SOCKET"
    elif [[ -z $DB_HOST || $DB_HOST == localhost ]]; then
        # PDO uses the socket for "localhost", so the grant is for user@localhost.
        connection="socket=/run/mysqld/mysqld.sock"
    else
        connection=$(printf 'host=%s\nport=%s' "$DB_HOST" "${DB_PORT:-3306}")
    fi
    info "MariaDB exporter will log in as DB_USERNAME=$DB_USERNAME from .env (global status and variables only)"
    printf '[client]\nuser=%s\npassword=%s\n%s\n' "$DB_USERNAME" "$(exporter_cnf_quote "$DB_PASSWORD")" "$connection" >"$WORK_DIR/mysqld-exporter.cnf"
    MYSQLD_EXPORTER_USER=app

    if ((!DRY_RUN)); then
        write_db_admin_cnf "$WORK_DIR/db-app.cnf" "$DB_USERNAME" "$DB_PASSWORD"
        printf '%s\n' "$connection" >>"$WORK_DIR/db-app.cnf"
        mariadb --defaults-extra-file="$WORK_DIR/db-app.cnf" -NBe 'SELECT 1' >/dev/null 2>"$WORK_DIR/mariadb-error" \
            || warn "DB_USERNAME=$DB_USERNAME could not log in ($(grep -m1 '^ERROR' "$WORK_DIR/mariadb-error" || tail -n1 "$WORK_DIR/mariadb-error")); the MariaDB panels stay empty until it can."
    fi
    install_file "$WORK_DIR/mysqld-exporter.cnf" /etc/prometheus/mysqld-exporter.cnf 0640 root:prometheus
    write_defaults_file prometheus-mysqld-exporter "ARGS=\"--web.listen-address=127.0.0.1:9104 --config.my-cnf=/etc/prometheus/mysqld-exporter.cnf --no-collect.slave_status --no-collect.info_schema.innodb_cmp --no-collect.info_schema.innodb_cmpmem --no-collect.info_schema.query_response_time\""
}

configure_mysqld_exporter() {
    if [[ -z $DB_ADMIN_USER && -n $DB_USERNAME ]]; then
        configure_mysqld_exporter_with_app_user
        return
    fi
    if [[ -z $DB_ADMIN_USER ]]; then
        warn "DB_USERNAME is empty or missing in $ENV_FILE, so the exporter cannot reuse the NNTmux database user; trying a dedicated MariaDB user instead."
    fi
    configure_mysqld_exporter_with_dedicated_user
}

# With --db-admin-user (or no DB_USERNAME in .env): a dedicated prometheus@localhost
# account with PROCESS/REPLICATION CLIENT and read access to performance_schema only.
configure_mysqld_exporter_with_dedicated_user() {
    local socket='' password='' admin_options
    MYSQLD_EXPORTER_USER=dedicated
    if loopback_host "$DB_HOST"; then
        local sql="CREATE USER IF NOT EXISTS 'prometheus'@'localhost' IDENTIFIED VIA unix_socket WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'prometheus'@'localhost';
GRANT SELECT ON performance_schema.* TO 'prometheus'@'localhost';"
        info "Creating MariaDB user prometheus@localhost (unix_socket, PROCESS/REPLICATION CLIENT, performance_schema read)"
        if ((DRY_RUN)); then
            echo "  would run as a MariaDB admin (--db-admin-user, root via socket, debian.cnf or DB_USERNAME): $sql"
        elif admin_options=$(mariadb_admin "$sql"); then
            # shellcheck disable=SC2086
            socket=$(mariadb $admin_options -NBe 'SELECT @@socket' 2>/dev/null || true)
        else
            warn "Could not create the MariaDB exporter user ($(grep -m1 '^ERROR' "$WORK_DIR/mariadb-error" || tail -n1 "$WORK_DIR/mariadb-error"))."
            echo "  Run this as a MariaDB admin (the exporter starts reporting once it exists):"
            printf '%s\n' "$sql" | sed 's/^/    /'
            echo "  or re-run with --db-admin-user=root --db-admin-password-file=/path/to/password-file"
        fi
        socket=${socket:-/run/mysqld/mysqld.sock}
        printf '[client]\nuser=prometheus\nsocket=%s\n' "$socket" >"$WORK_DIR/mysqld-exporter.cnf"
    else
        warn "DB_HOST=$DB_HOST is remote; create the exporter user there yourself:"
        password=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)
        echo "  CREATE USER 'prometheus'@'<this host>' IDENTIFIED BY '$password' WITH MAX_USER_CONNECTIONS 3;"
        echo "  GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'prometheus'@'<this host>';"
        echo "  GRANT SELECT ON performance_schema.* TO 'prometheus'@'<this host>';"
        printf '[client]\nuser=prometheus\npassword=%s\nhost=%s\nport=%s\n' "$password" "$DB_HOST" "${DB_PORT:-3306}" >"$WORK_DIR/mysqld-exporter.cnf"
    fi
    install_file "$WORK_DIR/mysqld-exporter.cnf" /etc/prometheus/mysqld-exporter.cnf 0640 root:prometheus
    write_defaults_file prometheus-mysqld-exporter "ARGS=\"--web.listen-address=127.0.0.1:9104 --config.my-cnf=/etc/prometheus/mysqld-exporter.cnf\""
}

detect_manticore() {
    MANTICORE_TARGET=
    [[ $SEARCH_DRIVER == manticore ]] || return 0
    local address
    address=$(scrape_address "${MANTICORE_HOST:-127.0.0.1}:${MANTICORE_PORT:-9308}")
    if [[ $(sys_http_get "http://$address/metrics" "$WORK_DIR/manticore") == 200 ]]; then
        MANTICORE_TARGET=$address
    else
        warn "Manticore did not answer http://$address/metrics (served by Manticore Buddy); skipping its scrape job."
    fi
}

configure_prometheus() {
    info "Writing /etc/prometheus/prometheus.yml"
    render_prometheus_config "$WORK_DIR/prometheus.yml"
    if ((!DRY_RUN)) && command -v promtool >/dev/null; then
        promtool check config "$WORK_DIR/prometheus.yml" >/dev/null || die "promtool rejected the generated config; see $WORK_DIR/prometheus.yml"
    fi
    install_file "$WORK_DIR/prometheus.yml" /etc/prometheus/prometheus.yml 0644 root:root
}

configure_grafana() {
    local web_group admin_password
    web_group=$(id -gn "$WEB_USER" 2>/dev/null || echo www-data)

    info "Preparing the Grafana JWT keypair in $STATE_DIR"
    run install -d -m 0750 -o root -g "$web_group" "$STATE_DIR"
    if [[ ! -f $ROOT$STATE_DIR/grafana-jwt.key ]]; then
        if ((DRY_RUN)); then
            echo "  would generate $STATE_DIR/grafana-jwt.key (0640 root:$web_group)"
        else
            openssl genrsa -out "$STATE_DIR/grafana-jwt.key" 2048 2>/dev/null
        fi
    fi
    run chown "root:$web_group" "$STATE_DIR/grafana-jwt.key"
    run chmod 0640 "$STATE_DIR/grafana-jwt.key"
    if ((!DRY_RUN)); then
        openssl rsa -in "$STATE_DIR/grafana-jwt.key" -pubout -out "$WORK_DIR/nntmux-jwt.pub" 2>/dev/null
        install_file "$WORK_DIR/nntmux-jwt.pub" /etc/grafana/nntmux-jwt.pub 0644 root:grafana
    else
        echo "  would write /etc/grafana/nntmux-jwt.pub (public half of the key)"
    fi

    admin_password=$(cat "$ROOT$STATE_DIR/grafana-admin-password" 2>/dev/null || true)
    if [[ -z $admin_password ]]; then
        admin_password=$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32)
        printf '%s\n' "$admin_password" >"$WORK_DIR/grafana-admin-password"
        install_file "$WORK_DIR/grafana-admin-password" "$STATE_DIR/grafana-admin-password" 0600 root:root
    fi

    info "Writing /etc/grafana/grafana.ini and NNTmux provisioning"
    if ((!DRY_RUN)) && [[ -f /etc/grafana/grafana.ini && ! -f /etc/grafana/grafana.ini.nntmux-orig ]]; then
        cp -p /etc/grafana/grafana.ini /etc/grafana/grafana.ini.nntmux-orig
    fi
    render_grafana_ini "$WORK_DIR/grafana.ini" "$admin_password"
    install_file "$WORK_DIR/grafana.ini" /etc/grafana/grafana.ini 0640 root:grafana
    install_file "$APP_PATH/docker/monitoring/grafana/provisioning/datasources/prometheus.yml" /etc/grafana/provisioning/datasources/nntmux-prometheus.yml 0640 root:grafana
    install_file "$APP_PATH/docker/monitoring/grafana/provisioning/dashboards/nntmux.yml" /etc/grafana/provisioning/dashboards/nntmux.yml 0640 root:grafana
    local dashboard
    for dashboard in "$APP_PATH"/docker/monitoring/grafana/dashboards/*.json; do
        install_file "$dashboard" "/etc/grafana/dashboards/nntmux/$(basename "$dashboard")" 0644 root:grafana
    done

    # grafana-server reads /etc/default/grafana-server as its EnvironmentFile.
    local defaults=$ROOT/etc/default/grafana-server
    {
        [[ -f $defaults ]] && grep -v '^PROMETHEUS_URL=' "$defaults"
        echo "PROMETHEUS_URL=http://127.0.0.1:$PROMETHEUS_PORT"
    } >"$WORK_DIR/grafana-server.default"
    install_file "$WORK_DIR/grafana-server.default" /etc/default/grafana-server 0644 root:root
}

configure_nginx() {
    render_nginx_snippets "$WORK_DIR/nntmux-grafana-map.conf" "$WORK_DIR/nntmux-grafana.conf"
    finish_nginx_snippet "$WORK_DIR/nntmux-grafana.conf"
    install_file "$WORK_DIR/nntmux-grafana-map.conf" /etc/nginx/conf.d/nntmux-grafana-map.conf 0644 root:root
    install_file "$WORK_DIR/nntmux-grafana.conf" /etc/nginx/snippets/nntmux-grafana.conf 0644 root:root

    local matches site target roots
    matches=$(nginx_sites_for_app | sort -u)
    if [[ -z $matches ]]; then
        warn "No nginx server block has a root that resolves to $APP_PATH/public."
        echo "  Roots found in the nginx config:"
        nginx_roots_seen | head -n 20
        echo "  Re-run with --nginx-site=/etc/nginx/sites-available/<your site>, or add this line inside"
        echo "  the NNTmux server block(s) yourself, then run: nginx -t && systemctl reload nginx"
        echo "    include snippets/nntmux-grafana.conf; $MARKER"
        return
    fi

    local -a edited=()
    while IFS= read -r site; do
        roots=$(awk -F'\t' -v f="$site" '$1 == f {print $2}' <<<"$matches")
        target=${site#"$ROOT"}
        nginx_add_include "$site" "$roots" >"$WORK_DIR/site"
        if cmp -s "$site" "$WORK_DIR/site"; then
            info "nginx site $target already includes the Grafana proxy"
        else
            info "Adding the Grafana proxy to nginx site $target"
            if ((!DRY_RUN)); then
                mkdir -p /var/backups/nntmux-monitoring
                cp -p "$site" "/var/backups/nntmux-monitoring/$(basename "$site").$(date +%Y%m%d%H%M%S)"
                cat "$WORK_DIR/site" >"$site"
            else
                mkdir -p -- "$DRY_DIR$(dirname -- "$target")"
                cp -- "$WORK_DIR/site" "$DRY_DIR$target"
                echo "  would update $target"
            fi
        fi
        edited+=("$site")
    done < <(cut -f1 <<<"$matches" | sort -u)

    if ((!DRY_RUN)); then
        if ! nginx -t 2>"$WORK_DIR/nginx-test"; then
            cat "$WORK_DIR/nginx-test" >&2
            for site in "${edited[@]}"; do
                nginx_remove_include "$site" >"$WORK_DIR/site.restored" && cat "$WORK_DIR/site.restored" >"$site"
            done
            rm -f /etc/nginx/snippets/nntmux-grafana.conf /etc/nginx/conf.d/nntmux-grafana-map.conf
            die "nginx -t failed; the NNTmux site was restored."
        fi
        systemctl reload nginx
    fi
    printf '%s\n' "${edited[@]#"$ROOT"}" | paste -sd' ' >"$WORK_DIR/nginx-site"
}

configure_apache() {
    render_apache_conf "$WORK_DIR/nntmux-grafana.conf"
    run a2enmod -q proxy proxy_http proxy_wstunnel headers
    install_file "$WORK_DIR/nntmux-grafana.conf" /etc/apache2/conf-available/nntmux-grafana.conf 0644 root:root
    run a2enconf -q nntmux-grafana
    if ((!DRY_RUN)); then
        if ! apachectl configtest 2>"$WORK_DIR/apache-test"; then
            cat "$WORK_DIR/apache-test" >&2
            a2disconf -q nntmux-grafana || true
            rm -f /etc/apache2/conf-available/nntmux-grafana.conf
            die "apachectl configtest failed; the Grafana proxy was removed again."
        fi
        systemctl reload apache2
    fi
}

configure_web_server() {
    if ((SKIP_WEBSERVER)) || [[ $WEB_SERVER == none ]]; then
        [[ $WEB_SERVER == none ]] && warn "Neither nginx nor apache2 is running."
        render_nginx_snippets "$WORK_DIR/map.conf" "$WORK_DIR/location.conf"
        finish_nginx_snippet "$WORK_DIR/location.conf"
        info "Web server not configured. Proxy /grafana/ to http://127.0.0.1:$GRAFANA_PORT, e.g. for nginx:"
        cat "$WORK_DIR/location.conf"
        return
    fi
    if [[ $WEB_SERVER == nginx ]]; then
        configure_nginx
    else
        configure_apache
    fi
}

env_set() {
    local key=$1 value=$2
    if grep -qE "^$key=" "$WORK_DIR/env"; then
        awk -v k="$key" -v v="$value" 'BEGIN { FS = OFS = "=" } $1 == k { print k "=" v; next } { print }' "$WORK_DIR/env" >"$WORK_DIR/env.new"
        mv "$WORK_DIR/env.new" "$WORK_DIR/env"
    else
        printf '%s=%s\n' "$key" "$value" >>"$WORK_DIR/env"
    fi
}

update_app_env() {
    local enabled=$1
    cp -- "$ENV_FILE" "$WORK_DIR/env"
    [[ -z $(tail -c1 "$WORK_DIR/env") ]] || echo >>"$WORK_DIR/env"
    env_set MONITORING_ENABLED "$enabled"
    if [[ $enabled == true ]]; then
        env_set GRAFANA_URL /grafana
        env_set GRAFANA_JWT_PRIVATE_KEY_PATH "$STATE_DIR/grafana-jwt.key"
        env_set MONITORING_PUSHGATEWAY_URL "http://127.0.0.1:$PUSHGATEWAY_PORT"
    fi
    if cmp -s "$ENV_FILE" "$WORK_DIR/env"; then
        return
    fi
    info "Updating $ENV_FILE (MONITORING_ENABLED=$enabled)"
    if ((DRY_RUN)); then
        mkdir -p -- "$DRY_DIR$(dirname -- "$ENV_FILE")"
        cp -- "$WORK_DIR/env" "$DRY_DIR$ENV_FILE"
        return
    fi
    cp -p -- "$ENV_FILE" "$ENV_FILE.bak-monitoring-$(date +%Y%m%d%H%M%S)"
    # Write in place so the file keeps its owner and mode.
    cat "$WORK_DIR/env" >"$ENV_FILE"
}

artisan() {
    local owner
    owner=$(stat -c %U "$ENV_FILE")
    run runuser -u "$owner" -- php "$APP_PATH/artisan" "$@"
}

refresh_app_config() {
    if [[ -f $APP_PATH/bootstrap/cache/config.php ]]; then
        artisan config:cache
    else
        artisan config:clear
    fi
}

save_state() {
    local installed=$1 web_server=$2 nginx_site=$3
    cat >"$WORK_DIR/install.state" <<STATE
# Components installed by NNTmux scripts/install-monitoring.sh. Anything not
# listed here (e.g. a pre-existing node_exporter) is never modified or removed.
packages=$installed
web_server=$web_server
nginx_site=$nginx_site
mysqld_exporter_user=${MYSQLD_EXPORTER_USER:-$(state_get mysqld_exporter_user)}
grafana_port=$GRAFANA_PORT
prometheus_port=$PROMETHEUS_PORT
pushgateway_port=$PUSHGATEWAY_PORT
STATE
    install_file "$WORK_DIR/install.state" "$STATE_DIR/install.state" 0644 root:root
}

start_services() {
    local package service
    info "Starting services"
    for package in $INSTALLED_PACKAGES; do
        service=$(service_for_package "$package")
        run systemctl enable "$service" >/dev/null 2>&1 || true
        run systemctl restart "$service"
    done
}

verify_install() {
    ((DRY_RUN)) && return
    local attempt
    info "Waiting for Prometheus and Grafana"
    for attempt in $(seq 1 30); do
        if [[ $(sys_http_get "http://127.0.0.1:$PROMETHEUS_PORT/-/ready" "$WORK_DIR/ready") == 200 && $(sys_http_get "http://127.0.0.1:$GRAFANA_PORT/grafana/api/health" "$WORK_DIR/health") == 200 ]]; then
            break
        fi
        ((attempt == 30)) && die "Prometheus or Grafana did not become ready; check: journalctl -u prometheus -u grafana-server"
        sleep 2
    done
    artisan monitoring:export-metrics || warn "The first metrics push failed; it is retried every minute by the scheduler."
    sleep 16
    info "Scrape targets"
    sys_http_get "http://127.0.0.1:$PROMETHEUS_PORT/api/v1/targets" "$WORK_DIR/targets" >/dev/null
    # shellcheck disable=SC2016 # PHP code, not shell.
    php -r '$t = json_decode(file_get_contents($argv[1]), true)["data"]["activeTargets"] ?? [];
        foreach ($t as $x) { printf("  %-14s %-6s %s %s\n", $x["labels"]["job"], $x["health"], $x["scrapeUrl"], $x["lastError"]); }' "$WORK_DIR/targets" || true
}

# ── Uninstall ────────────────────────────────────────────────
uninstall() {
    [[ -f $STATE_FILE ]] || die "Nothing to uninstall: $STATE_FILE not found."
    local packages web_server nginx_site package
    packages=$(state_get packages)
    web_server=$(state_get web_server)
    nginx_site=$(state_get nginx_site)

    if [[ $web_server == nginx ]]; then
        info "Removing the Grafana proxy from nginx"
        local site
        for site in $nginx_site; do
            [[ -f $site ]] || continue
            nginx_remove_include "$site" >"$WORK_DIR/site"
            ((DRY_RUN)) || cat "$WORK_DIR/site" >"$site"
        done
        run rm -f /etc/nginx/snippets/nntmux-grafana.conf /etc/nginx/conf.d/nntmux-grafana-map.conf
        if ((!DRY_RUN)); then nginx -t && systemctl reload nginx; fi
    elif [[ $web_server == apache ]]; then
        info "Removing the Grafana proxy from Apache"
        run a2disconf -q nntmux-grafana || true
        run rm -f /etc/apache2/conf-available/nntmux-grafana.conf
        if ((!DRY_RUN)); then apachectl configtest && systemctl reload apache2; fi
    fi

    info "Stopping services installed by this script: $packages"
    for package in $packages; do
        run systemctl disable --now "$(service_for_package "$package")" || true
    done

    update_app_env false
    refresh_app_config

    if ((PURGE)); then
        info "Purging packages, configuration and data"
        run apt-mark unhold grafana || true
        # shellcheck disable=SC2086
        run env DEBIAN_FRONTEND=noninteractive apt-get purge -y -q $packages
        run rm -f /etc/apt/sources.list.d/grafana.list /etc/apt/keyrings/grafana.gpg
        # Only Prometheus' own TSDB: /var/lib/prometheus/node-exporter may belong to an exporter we never installed.
        run rm -rf /etc/grafana/dashboards/nntmux /var/lib/grafana /var/lib/prometheus/metrics2
        run rm -f /etc/prometheus/mysqld-exporter.cnf /etc/prometheus/node-exporter.password /etc/prometheus/prometheus.yml \
            /etc/grafana/nntmux-jwt.pub /etc/grafana/grafana.ini /etc/grafana/grafana.ini.nntmux-orig \
            /etc/grafana/provisioning/datasources/nntmux-prometheus.yml /etc/grafana/provisioning/dashboards/nntmux.yml
        run rmdir --ignore-fail-on-non-empty /etc/prometheus /etc/grafana/provisioning/datasources /etc/grafana/provisioning/dashboards /etc/grafana/provisioning /etc/grafana /etc/grafana/dashboards
        if loopback_host "$DB_HOST" && [[ $(state_get mysqld_exporter_user) == dedicated ]]; then
            if ((DRY_RUN)); then
                echo "  would drop MariaDB user prometheus@localhost"
            elif ! mariadb_admin "DROP USER IF EXISTS 'prometheus'@'localhost';" >/dev/null; then
                warn "Could not drop MariaDB user prometheus@localhost; remove it manually."
            fi
        fi
        run rm -rf "$STATE_DIR"
    fi
    info "Monitoring removed. Components that were already installed before were left untouched."
}

# ── Main ─────────────────────────────────────────────────────
main() {
    if [[ $MODE == detect ]]; then
        run_detection
        print_detection
        return
    fi

    if ((!DRY_RUN)) && [[ $(id -u) -ne 0 ]]; then
        die 'Run as root (sudo), or use --detect / --dry-run.'
    fi

    if [[ $MODE == uninstall ]]; then
        uninstall
        return
    fi

    local os_id='' os_version=''
    if [[ -r $ROOT/etc/os-release ]]; then
        # shellcheck disable=SC1091
        os_id=$(. "$ROOT/etc/os-release" && echo "${ID:-}")
        # shellcheck disable=SC1091
        os_version=$(. "$ROOT/etc/os-release" && echo "${VERSION_ID:-}")
    fi
    if [[ $os_id != ubuntu || ! $os_version =~ ^(22\.04|24\.04)$ ]]; then
        ((DRY_RUN)) || die "Ubuntu 22.04 or 24.04 is required (found ${os_id:-unknown} ${os_version})."
        warn "Not Ubuntu 22.04/24.04 (${os_id:-unknown} ${os_version}); continuing because of --dry-run."
    fi

    adopt_partial_install
    run_detection
    check_core_component Prometheus prometheus "$PROMETHEUS_PORT"
    check_core_component Pushgateway prometheus-pushgateway "$PUSHGATEWAY_PORT"
    check_core_component Grafana grafana "$GRAFANA_PORT"
    local name
    for name in node mysqld redis search; do
        if [[ ${DETECTED[$name]} == absent ]] && port_in_use "${EXPORTER_PORT[$name]}"; then
            die "Port ${EXPORTER_PORT[$name]} (for the $name exporter) is used by $(port_owner "${EXPORTER_PORT[$name]}"). Pass --$name-exporter=existing --$name-exporter-url=… or --$name-exporter=skip."
        fi
    done
    print_detection
    ((DRY_RUN)) && info "Dry run: files are rendered under $DRY_DIR"

    INSTALLED_PACKAGES=
    install_packages
    configure_exporters
    detect_manticore
    configure_prometheus
    configure_grafana
    configure_web_server
    local nginx_site=''
    [[ -f $WORK_DIR/nginx-site ]] && nginx_site=$(<"$WORK_DIR/nginx-site")
    save_state "$INSTALLED_PACKAGES" "$( ((SKIP_WEBSERVER)) && echo none || echo "$WEB_SERVER")" "$nginx_site"
    start_services
    update_app_env true
    refresh_app_config
    verify_install

    info "Done. Open $APP_URL/admin/monitoring (the Laravel scheduler must be running for NNTmux metrics)."
}

main
