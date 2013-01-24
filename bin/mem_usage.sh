#!/usr/bin/env bash

free -m|awk '/^Mem:/{print "\033[1;31mRam Used:\033[0m " $3 "MB, \033[1;31mRam Free:\033[0m " $4 "MB"}';
free -m|awk '/^Swap:/{print "\033[1;31mSwap Used:\033[0m " $3 "MB, \033[1;31mSwap Free:\033[0m " $4 "MB"}'
uptime | cut -d ',' -f 2-
