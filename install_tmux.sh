#!/usr/bin/env bash

## This script will install latest tmux from source
## script is used from https://bogdanvlviv.com/posts/tmux/how-to-install-the-latest-tmux-on-ubuntu-16_04.html with small additions

sudo apt update

sudo apt install -y git

sudo apt install -y automake
sudo apt install -y build-essential
sudo apt install -y pkg-config
sudo apt install -y libevent-dev
sudo apt install -y libncurses5-dev
sudo apt install -y fonts-powerline
sudo apt install -y powerline
sudo apt install -y bison
sudo apt install -y byacc

rm -fr /tmp/tmux

git clone https://github.com/tmux/tmux.git /tmp/tmux

cd /tmp/tmux

git fetch --all --tags --prune

git checkout 3.5a

sh autogen.sh

./configure && make

sudo make install

cd -

rm -fr /tmp/tmux
