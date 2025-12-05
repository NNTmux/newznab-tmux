#!/bin/bash
#
# Redis Monitor - Modern visual monitoring for Redis server
# Usage: redis-monitor.sh [host] [port] [refresh_interval] [password]
#        redis-monitor.sh 127.0.0.1 6379 30 mypassword
#
# Or set REDIS_PASSWORD environment variable:
#        export REDIS_PASSWORD=mypassword
#        redis-monitor.sh
#

# Configuration
REDIS_HOST="${1:-127.0.0.1}"
REDIS_PORT="${2:-6379}"
REFRESH="${3:-30}"
REDIS_PASSWORD="${4:-${REDIS_PASSWORD}}"  # Accept as arg or env var

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
GRAY='\033[0;90m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m' # No Color

# Box drawing characters
H_LINE="─"
V_LINE="│"
TL_CORNER="┌"
TR_CORNER="┐"
BL_CORNER="└"
BR_CORNER="┘"
T_DOWN="┬"
T_UP="┴"
T_RIGHT="├"
T_LEFT="┤"
CROSS="┼"

# Get terminal width
TERM_WIDTH=$(tput cols 2>/dev/null || echo 80)
BOX_WIDTH=$((TERM_WIDTH - 2))

# Clear to end of line escape sequence
CLEAR_EOL='\033[K'

# Functions
print_header() {
    local title="$1"
    local padding=$(( (BOX_WIDTH - ${#title} - 2) / 2 ))
    echo -e "${CYAN}${TL_CORNER}$(printf '%*s' "$BOX_WIDTH" | tr ' ' "$H_LINE")${TR_CORNER}${NC}${CLEAR_EOL}"
    echo -e "${CYAN}${V_LINE}${NC}$(printf '%*s' "$padding")${BOLD}${WHITE}$title${NC}$(printf '%*s' "$((BOX_WIDTH - padding - ${#title}))")${CYAN}${V_LINE}${NC}${CLEAR_EOL}"
    echo -e "${CYAN}${T_RIGHT}$(printf '%*s' "$BOX_WIDTH" | tr ' ' "$H_LINE")${T_LEFT}${NC}${CLEAR_EOL}"
}

print_section() {
    local title="$1"
    echo -e "${CYAN}${T_RIGHT}${H_LINE}${H_LINE}${NC} ${YELLOW}${BOLD}$title${NC} ${CYAN}$(printf '%*s' "$((BOX_WIDTH - ${#title} - 5))" | tr ' ' "$H_LINE")${T_LEFT}${NC}${CLEAR_EOL}"
}

print_row() {
    local label="$1"
    local value="$2"
    local color="${3:-$WHITE}"
    local label_width=25
    local value_width=$((BOX_WIDTH - label_width - 3))
    printf "${CYAN}${V_LINE}${NC} ${DIM}%-${label_width}s${NC} ${color}%-${value_width}s${NC}${CYAN}${V_LINE}${NC}${CLEAR_EOL}\n" "$label" "$value"
}

print_meter() {
    local label="$1"
    local current="$2"
    local max="$3"
    local label_width=25
    local meter_width=30
    local percentage=0

    if [ "$max" -gt 0 ] 2>/dev/null; then
        percentage=$((current * 100 / max))
    fi

    local filled=$((percentage * meter_width / 100))
    local empty=$((meter_width - filled))

    local color=$GREEN
    if [ "$percentage" -gt 80 ]; then
        color=$RED
    elif [ "$percentage" -gt 60 ]; then
        color=$YELLOW
    fi

    local meter="${color}"
    for ((i=0; i<filled; i++)); do meter+="█"; done
    meter+="${GRAY}"
    for ((i=0; i<empty; i++)); do meter+="░"; done
    meter+="${NC}"

    printf "${CYAN}${V_LINE}${NC} ${DIM}%-${label_width}s${NC} %b ${WHITE}%3d%%${NC}${CYAN}${V_LINE}${NC}${CLEAR_EOL}\n" "$label" "$meter" "$percentage"
}

print_footer() {
    echo -e "${CYAN}${BL_CORNER}$(printf '%*s' "$BOX_WIDTH" | tr ' ' "$H_LINE")${BR_CORNER}${NC}${CLEAR_EOL}"
}

format_bytes() {
    local bytes=$1
    if [ "$bytes" -ge 1073741824 ]; then
        echo "$(echo "scale=2; $bytes / 1073741824" | bc) GB"
    elif [ "$bytes" -ge 1048576 ]; then
        echo "$(echo "scale=2; $bytes / 1048576" | bc) MB"
    elif [ "$bytes" -ge 1024 ]; then
        echo "$(echo "scale=2; $bytes / 1024" | bc) KB"
    else
        echo "$bytes B"
    fi
}

format_number() {
    echo "$1" | sed ':a;s/\B[0-9]\{3\}\>$/,&/;ta'
}

get_redis_info() {
    if [ -n "$REDIS_PASSWORD" ]; then
        redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" -a "$REDIS_PASSWORD" --no-auth-warning info 2>/dev/null
    else
        redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" info 2>/dev/null
    fi
}

# Terminal control for flicker-free updates
hide_cursor() { printf '\033[?25l'; }
show_cursor() { printf '\033[?25h'; }
move_to_top() { printf '\033[H'; }
clear_screen() { printf '\033[2J\033[H'; }

# Cleanup on exit
cleanup() {
    show_cursor
    printf '\033[?1049l'  # Exit alternate screen buffer
    exit 0
}
trap cleanup EXIT INT TERM

# Enter alternate screen buffer and hide cursor
printf '\033[?1049h'
hide_cursor
clear_screen

# Main loop
while true; do
    # Move cursor to top-left instead of clearing (prevents flicker)
    move_to_top

    # Get Redis info
    INFO=$(get_redis_info)

    if [ -z "$INFO" ]; then
        clear_screen
        print_header "⚠ REDIS MONITOR - CONNECTION FAILED"
        print_row "Status" "Cannot connect to Redis" "$RED"
        print_row "Host" "$REDIS_HOST:$REDIS_PORT" "$YELLOW"
        print_footer
        echo -e "${DIM}Press Ctrl+C to exit | Refresh: ${REFRESH}s | Retrying...${NC}"
        sleep "$REFRESH"
        continue
    fi

    # Parse info
    REDIS_VERSION=$(echo "$INFO" | grep "^redis_version:" | cut -d: -f2 | tr -d '\r')
    UPTIME_DAYS=$(echo "$INFO" | grep "^uptime_in_days:" | cut -d: -f2 | tr -d '\r')
    UPTIME_SECONDS=$(echo "$INFO" | grep "^uptime_in_seconds:" | cut -d: -f2 | tr -d '\r')
    CONNECTED_CLIENTS=$(echo "$INFO" | grep "^connected_clients:" | cut -d: -f2 | tr -d '\r')
    BLOCKED_CLIENTS=$(echo "$INFO" | grep "^blocked_clients:" | cut -d: -f2 | tr -d '\r')
    USED_MEMORY=$(echo "$INFO" | grep "^used_memory:" | cut -d: -f2 | tr -d '\r')
    USED_MEMORY_PEAK=$(echo "$INFO" | grep "^used_memory_peak:" | cut -d: -f2 | tr -d '\r')
    USED_MEMORY_RSS=$(echo "$INFO" | grep "^used_memory_rss:" | cut -d: -f2 | tr -d '\r')
    MAXMEMORY=$(echo "$INFO" | grep "^maxmemory:" | cut -d: -f2 | tr -d '\r')
    TOTAL_CONNECTIONS=$(echo "$INFO" | grep "^total_connections_received:" | cut -d: -f2 | tr -d '\r')
    TOTAL_COMMANDS=$(echo "$INFO" | grep "^total_commands_processed:" | cut -d: -f2 | tr -d '\r')
    OPS_PER_SEC=$(echo "$INFO" | grep "^instantaneous_ops_per_sec:" | cut -d: -f2 | tr -d '\r')
    INPUT_KBPS=$(echo "$INFO" | grep "^instantaneous_input_kbps:" | cut -d: -f2 | tr -d '\r')
    OUTPUT_KBPS=$(echo "$INFO" | grep "^instantaneous_output_kbps:" | cut -d: -f2 | tr -d '\r')
    KEYSPACE_HITS=$(echo "$INFO" | grep "^keyspace_hits:" | cut -d: -f2 | tr -d '\r')
    KEYSPACE_MISSES=$(echo "$INFO" | grep "^keyspace_misses:" | cut -d: -f2 | tr -d '\r')
    EXPIRED_KEYS=$(echo "$INFO" | grep "^expired_keys:" | cut -d: -f2 | tr -d '\r')
    EVICTED_KEYS=$(echo "$INFO" | grep "^evicted_keys:" | cut -d: -f2 | tr -d '\r')
    DB_KEYS=$(echo "$INFO" | grep "^db0:" | sed 's/.*keys=\([0-9]*\).*/\1/' | tr -d '\r')
    ROLE=$(echo "$INFO" | grep "^role:" | cut -d: -f2 | tr -d '\r')
    CONNECTED_SLAVES=$(echo "$INFO" | grep "^connected_slaves:" | cut -d: -f2 | tr -d '\r')
    MEM_FRAG_RATIO=$(echo "$INFO" | grep "^mem_fragmentation_ratio:" | cut -d: -f2 | tr -d '\r')
    RDB_CHANGES=$(echo "$INFO" | grep "^rdb_changes_since_last_save:" | cut -d: -f2 | tr -d '\r')
    RDB_LAST_SAVE=$(echo "$INFO" | grep "^rdb_last_save_time:" | cut -d: -f2 | tr -d '\r')

    # Calculate hit rate
    HIT_RATE=0
    if [ -n "$KEYSPACE_HITS" ] && [ -n "$KEYSPACE_MISSES" ]; then
        TOTAL_OPS=$((KEYSPACE_HITS + KEYSPACE_MISSES))
        if [ "$TOTAL_OPS" -gt 0 ]; then
            HIT_RATE=$((KEYSPACE_HITS * 100 / TOTAL_OPS))
        fi
    fi

    # Current time
    CURRENT_TIME=$(date "+%Y-%m-%d %H:%M:%S")

    # Print dashboard
    print_header "🔴 REDIS MONITOR v${REDIS_VERSION:-unknown} | ${REDIS_HOST}:${REDIS_PORT}"

    print_section "Server Status"
    print_row "Status" "● ONLINE" "$GREEN"
    print_row "Role" "${ROLE:-standalone}" "$MAGENTA"
    print_row "Uptime" "${UPTIME_DAYS:-0} days ($(format_number ${UPTIME_SECONDS:-0}) seconds)" "$WHITE"
    print_row "Last Updated" "$CURRENT_TIME" "$GRAY"

    print_section "Memory Usage"
    USED_MEM_FMT=$(format_bytes ${USED_MEMORY:-0})
    PEAK_MEM_FMT=$(format_bytes ${USED_MEMORY_PEAK:-0})
    RSS_MEM_FMT=$(format_bytes ${USED_MEMORY_RSS:-0})
    print_row "Used Memory" "$USED_MEM_FMT" "$CYAN"
    print_row "Peak Memory" "$PEAK_MEM_FMT" "$YELLOW"
    print_row "RSS Memory" "$RSS_MEM_FMT" "$WHITE"
    print_row "Fragmentation Ratio" "${MEM_FRAG_RATIO:-N/A}" "$WHITE"

    if [ -n "$MAXMEMORY" ] && [ "$MAXMEMORY" -gt 0 ]; then
        print_meter "Memory Usage" "${USED_MEMORY:-0}" "$MAXMEMORY"
    fi

    print_section "Clients & Connections"
    print_row "Connected Clients" "$(format_number ${CONNECTED_CLIENTS:-0})" "$GREEN"
    print_row "Blocked Clients" "$(format_number ${BLOCKED_CLIENTS:-0})" "$YELLOW"
    print_row "Total Connections" "$(format_number ${TOTAL_CONNECTIONS:-0})" "$WHITE"
    [ -n "$CONNECTED_SLAVES" ] && print_row "Connected Slaves" "$CONNECTED_SLAVES" "$MAGENTA"

    print_section "Performance Metrics"
    print_row "Commands Processed" "$(format_number ${TOTAL_COMMANDS:-0})" "$WHITE"
    print_row "Operations/sec" "$(format_number ${OPS_PER_SEC:-0}) ops/s" "$GREEN"
    print_row "Network Input" "${INPUT_KBPS:-0} KB/s" "$CYAN"
    print_row "Network Output" "${OUTPUT_KBPS:-0} KB/s" "$CYAN"

    print_section "Keyspace Statistics"
    print_row "Total Keys (db0)" "$(format_number ${DB_KEYS:-0})" "$WHITE"
    print_row "Keyspace Hits" "$(format_number ${KEYSPACE_HITS:-0})" "$GREEN"
    print_row "Keyspace Misses" "$(format_number ${KEYSPACE_MISSES:-0})" "$RED"
    print_row "Hit Rate" "${HIT_RATE}%" "$CYAN"
    print_row "Expired Keys" "$(format_number ${EXPIRED_KEYS:-0})" "$YELLOW"
    print_row "Evicted Keys" "$(format_number ${EVICTED_KEYS:-0})" "$RED"

    print_section "Persistence"
    print_row "Changes Since Save" "$(format_number ${RDB_CHANGES:-0})" "$WHITE"
    if [ -n "$RDB_LAST_SAVE" ] && [ "$RDB_LAST_SAVE" -gt 0 ]; then
        LAST_SAVE_FMT=$(date -d "@$RDB_LAST_SAVE" "+%Y-%m-%d %H:%M:%S" 2>/dev/null || echo "N/A")
        print_row "Last Save" "$LAST_SAVE_FMT" "$GRAY"
    fi

    print_footer
    echo -e "${DIM}Press Ctrl+C to exit | Refresh: ${REFRESH}s${NC}${CLEAR_EOL}"
    # Clear any remaining lines below (in case previous frame had more content)
    printf '\033[J'

    sleep "$REFRESH"
done

