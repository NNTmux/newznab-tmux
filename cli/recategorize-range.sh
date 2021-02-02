#!/bin/bash

D="\e[39m"
G="\e[32m"
M="\e[91m"

if [[ ! $1 || ! $2 || ! $3 ]]; then
    echo -e "${G}This script chunks calls to misc/testing/Releases/recategorize.php to work around memory issues."
    echo -e "It also automatically retries when a chunk is halted due to low memory.${D}"
    echo -e "${M}Three parameters are required:${D}"
    echo "1. start position"
    echo "2. stop position"
    echo "3. batch size"
    echo -e "${G}You can send shortened ints, i.e. 3m for 3000000 or 2.5k for 2500.${D}"
    exit 1
fi

function toInt {
    local multiplier=$([[ "$1" == *"m"* ]] && echo 1000000 || echo 1)
    multiplier=$([[ "$1" == *"k"* ]] && echo 1000 || echo $multiplier)
    local num=${1/[mk]/}
    num=$(echo "$num * $multiplier" | bc)
    num=${num%.*}

    echo $num
}

start=$(toInt $1)
stop=$(toInt $2)
batchSize=$(toInt $3)

echo "Expanded params: start $start, stop $stop, batchSize $batchSize"

start=$(( start / batchSize ))
stop=$(( stop / batchSize ))

for batch in $(seq $start $stop); do
    offset=$(( $batch * $batchSize ))

    echo -e "${G}${offset}${D}"
    while true; do
        php misc/testing/Releases/recategorize.php all notest $offset $batchSize
        if [[ $? != 27 ]]; then
            echo -e "${M}${offset}${D}"
            exit
        else
            date
            sleep 3
        fi
    done
done
