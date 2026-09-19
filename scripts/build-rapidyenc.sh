#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/build-rapidyenc.sh
# Requires curl (first download only), sha256sum, tar, CMake and a C++ compiler.
# Source and library live in storage/app/rapidyenc, relative to this checkout.
# Rebuilds replace the library atomically; existing workers keep their loaded copy.
if [[ $# -ne 0 || $(uname -s) != Linux ]]; then
    echo 'Usage: scripts/build-rapidyenc.sh (no arguments; Linux only)' >&2
    exit 2
fi
revision=480bd7b5896f8b3edecc721d23f1384d767ffe2f
checksum=bd5eff1e978672af4ebaacde837270a679808bbc12dc15bd6acf7c83d94baa16
project_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
output_dir="$project_root/storage/app/rapidyenc"
archive="$output_dir/rapidyenc-$revision.tar.gz"
for required in cmake sha256sum tar; do
    command -v "$required" >/dev/null || { echo "Missing build prerequisite: $required" >&2; exit 1; }
done
mkdir -p -- "$output_dir"
work_dir=$(mktemp -d "$output_dir/.build.XXXXXX")
trap 'rm -rf -- "$work_dir"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
if [[ ! -f "$archive" ]]; then
    curl --fail --location --proto '=https' --tlsv1.2 \
        "https://codeload.github.com/animetosho/rapidyenc/tar.gz/$revision" --output "$work_dir/source.tar.gz"
    printf '%s  %s\n' "$checksum" "$work_dir/source.tar.gz" | sha256sum --check --status
    mv -- "$work_dir/source.tar.gz" "$archive"
fi
if ! printf '%s  %s\n' "$checksum" "$archive" | sha256sum --check --status; then
    echo "Source checksum mismatch: $archive. Remove this archive and rerun to download it again." >&2
    exit 1
fi
mkdir "$work_dir/source"
tar -xzf "$archive" --strip-components=1 -C "$work_dir/source"
cmake -S "$work_dir/source" -B "$work_dir/build" \
    -DCMAKE_BUILD_TYPE=Release -DBUILD_NATIVE=OFF \
    -DDISABLE_ENCODE=ON -DDISABLE_TOOL=ON
cmake --build "$work_dir/build" --target rapidyenc_shared --parallel 2
mv -fT -- "$work_dir/build/librapidyenc.so" "$output_dir/librapidyenc.so"
printf 'Source archive: %s\nLibrary: %s/librapidyenc.so\n' "$archive" "$output_dir"
printf 'YENC_DECODER=auto uses this library by default. Restart CLI workers after rebuilding.\n'
