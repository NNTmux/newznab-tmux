#!/usr/bin/env bash
set -Eeuo pipefail

# Build in an owned temporary directory; leave unrelated files in /tmp alone.
build_dir="$(mktemp -d "${TMPDIR:-/tmp}/nntmux-tmux.XXXXXXXX")"
trap 'rm -rf -- "$build_dir"' EXIT

sudo apt update
sudo apt install -y git automake build-essential pkg-config libevent-dev \
    libncurses-dev fonts-powerline powerline bison byacc

git clone --branch 3.7b --depth 1 https://github.com/tmux/tmux.git "$build_dir/source"
cd "$build_dir/source"
sh autogen.sh
./configure
make
sudo make install
