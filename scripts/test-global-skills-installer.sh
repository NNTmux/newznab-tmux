#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
INSTALLER="${SCRIPT_DIR}/install-global-skills.sh"
GENERATOR="${SCRIPT_DIR}/generate-global-skills-installer.sh"
LOCK_FILE="${PROJECT_ROOT}/skills-lock.json"
TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/global-skills-test.XXXXXX")"

cleanup() {
    rm -rf -- "${TEMP_ROOT}"
}
trap cleanup EXIT INT TERM

fail() {
    printf 'Test failure: %s\n' "$1" >&2
    exit 1
}

assert_contains() {
    local needle="$1"
    local haystack="$2"

    [[ "${haystack}" == *"${needle}"* ]] || fail "Expected output to contain: ${needle}"
}

assert_file_exists() {
    [[ -e "$1" ]] || fail "Expected file to exist: $1"
}

assert_equals() {
    local expected="$1"
    local actual="$2"
    local description="$3"

    [[ "${expected}" == "${actual}" ]] || fail "${description}: expected ${expected}, got ${actual}"
}

expected_skill_count="$(awk '
    /^SKILLS=\(/ { inside = 1; next }
    inside && /^\)/ { inside = 0 }
    inside && /^    / { count++ }
    END { print count + 0 }
' "${INSTALLER}")"
assert_equals 119 "${expected_skill_count}" "generated skill count"

generated_installer="${TEMP_ROOT}/generated-installer.sh"
"${GENERATOR}" "${LOCK_FILE}" "${generated_installer}" >/dev/null
bash -n "${generated_installer}"
generated_count="$(awk '
    /^SKILLS=\(/ { inside = 1; next }
    inside && /^\)/ { inside = 0 }
    inside && /^    / { count++ }
    END { print count + 0 }
' "${generated_installer}")"
assert_equals "${expected_skill_count}" "${generated_count}" "regenerated skill count"
assert_equals 5 "$(rg -c '^run_command npx --yes skills add' "${generated_installer}")" "source command count"
if ! diff -u \
    <(awk '/^    "[^"]+": \{$/ { skill = $1; gsub(/[":]/, "", skill); print skill }' "${LOCK_FILE}" | LC_ALL=C sort) \
    <(awk '
        /^SKILLS=\(/ { inside = 1; next }
        inside && /^\)/ { inside = 0 }
        inside && /^    / { print $1 }
    ' "${generated_installer}" | LC_ALL=C sort); then
    fail 'generated installer skill names differ from skills-lock.json'
fi

mock_bin="${TEMP_ROOT}/bin"
mkdir -p -- "${mock_bin}"
cat >"${mock_bin}/npx" <<'MOCK_NPX'
#!/usr/bin/env bash

set -Eeuo pipefail

printf '%s\n' "$*" >>"${MOCK_NPX_LOG}"
[[ "${1:-}" == "--yes" ]] || exit 2
shift
[[ "${1:-}" == "skills" ]] || exit 2
shift
[[ "${1:-}" == "add" ]] || exit 2
shift
shift

while (( $# > 0 )); do
    case "$1" in
        --global|--yes)
            shift
            ;;
        --skill)
            skill="$2"
            mkdir -p -- "${SKILLS_GLOBAL_DIR}/${skill}"
            printf 'mock skill: %s\n' "${skill}" >"${SKILLS_GLOBAL_DIR}/${skill}/SKILL.md"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done
MOCK_NPX
chmod 755 "${mock_bin}/npx"

run_installer() {
    local home_dir="$1"
    local shared_dir="$2"
    local codex_home="$3"
    shift 3

    HOME="${home_dir}" \
        SKILLS_GLOBAL_DIR="${shared_dir}" \
        CODEX_HOME="${codex_home}" \
        MOCK_NPX_LOG="${TEMP_ROOT}/npx.log" \
        PATH="${mock_bin}:${PATH}" \
        "${INSTALLER}" "$@"
}

home_dir="${TEMP_ROOT}/home"
shared_dir="${TEMP_ROOT}/shared"
codex_home="${TEMP_ROOT}/codex"
mkdir -p -- "${home_dir}" "${codex_home}/skills/.system"
printf 'preserve me\n' >"${codex_home}/skills/.system/SKILL.md"
: >"${TEMP_ROOT}/npx.log"
run_installer "${home_dir}" "${shared_dir}" "${codex_home}" >"${TEMP_ROOT}/install.log" 2>&1 \
    || { cat "${TEMP_ROOT}/install.log" >&2; fail 'initial installation failed'; }

assert_equals "${expected_skill_count}" "$(find "${codex_home}/skills" -mindepth 1 -maxdepth 1 -type l | wc -l | tr -d ' ')" "symlink count"
assert_file_exists "${codex_home}/skills/.system/SKILL.md"
system_contents="$(<"${codex_home}/skills/.system/SKILL.md")"
assert_contains 'preserve me' "${system_contents}"
npx_call_count="$(wc -l < "${TEMP_ROOT}/npx.log" | tr -d ' ')"
assert_equals 5 "${npx_call_count}" "global source install count"
npx_log_contents="$(<"${TEMP_ROOT}/npx.log")"
assert_contains '--global' "${npx_log_contents}"

run_installer "${home_dir}" "${shared_dir}" "${codex_home}" >"${TEMP_ROOT}/rerun.log" 2>&1 \
    || { cat "${TEMP_ROOT}/rerun.log" >&2; fail 'idempotent symlink rerun failed'; }
assert_equals "${expected_skill_count}" "$(rg -c '^Already linked:' "${TEMP_ROOT}/rerun.log")" "idempotent link count"

dry_home="${TEMP_ROOT}/dry-home"
dry_shared="${TEMP_ROOT}/dry-shared"
dry_codex="${TEMP_ROOT}/dry-codex"
dry_output="$(run_installer "${dry_home}" "${dry_shared}" "${dry_codex}" --dry-run 2>&1)"
assert_contains 'Would bridge skills' "${dry_output}"
[[ ! -e "${dry_shared}" ]] || fail 'dry run created the shared skill directory'
[[ ! -e "${dry_codex}" ]] || fail 'dry run created the Codex directory'

copy_home="${TEMP_ROOT}/copy-home"
copy_shared="${TEMP_ROOT}/copy-shared"
copy_codex="${TEMP_ROOT}/copy-codex"
mkdir -p -- "${copy_home}" "${copy_codex}/skills/.system"
run_installer "${copy_home}" "${copy_shared}" "${copy_codex}" --copy >"${TEMP_ROOT}/copy.log" 2>&1 \
    || { cat "${TEMP_ROOT}/copy.log" >&2; fail 'copy installation failed'; }
assert_equals "${expected_skill_count}" "$(find "${copy_codex}/skills" -mindepth 1 -maxdepth 1 -type d ! -name .system | wc -l | tr -d ' ')" "copy count"
run_installer "${copy_home}" "${copy_shared}" "${copy_codex}" --copy >"${TEMP_ROOT}/copy-rerun.log" 2>&1 \
    || { cat "${TEMP_ROOT}/copy-rerun.log" >&2; fail 'idempotent copy rerun failed'; }
assert_equals "${expected_skill_count}" "$(rg -c '^Already copied:' "${TEMP_ROOT}/copy-rerun.log")" "idempotent copy count"

conflict_home="${TEMP_ROOT}/conflict-home"
conflict_shared="${TEMP_ROOT}/conflict-shared"
conflict_codex="${TEMP_ROOT}/conflict-codex"
first_skill="$(awk '
    /^SKILLS=\(/ { inside = 1; next }
    inside && /^    / { print $1; exit }
' "${INSTALLER}")"
mkdir -p -- "${conflict_home}" "${conflict_codex}/skills/${first_skill}"
printf 'unmanaged\n' >"${conflict_codex}/skills/${first_skill}/marker.txt"
if run_installer "${conflict_home}" "${conflict_shared}" "${conflict_codex}" >"${TEMP_ROOT}/conflict.log" 2>&1; then
    cat "${TEMP_ROOT}/conflict.log" >&2
    fail 'unmanaged conflict was not rejected'
fi
conflict_log_contents="$(<"${TEMP_ROOT}/conflict.log")"
assert_contains 'Refusing to overwrite existing path' "${conflict_log_contents}"

printf 'Global skills installer tests passed (%d skills).\n' "${expected_skill_count}"
