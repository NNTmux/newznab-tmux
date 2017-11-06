#!/bin/sh

if [ -e "NNBase.php" ]
then
	export NNTMUX_ROOT="$(pwd)"
else
	export NNTMUX_ROOT="$(php ../../../../../NNBase.php)"
fi

export NNTMUX_PATH="${NNTMUX_ROOT}/misc/update"
export TEST_PATH="${NNTMUX_ROOT}/misc/testing"
export NNTMUX_SLEEP_TIME="60" # in seconds
LASTOPTIMIZE=`date +%s`
LASTOPTIMIZE1=`date +%s`
command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }

while :

 do
CURRTIME=`date +%s`

tmux kill-session -t NNTPProxy
$PHP ${NNTMUX_PATH}/nntpproxy.php

cd ${NNTMUX_PATH}
$PHP ${NNTMUX_PATH}/update_binaries.php


$PHP ${NNTMUX_PATH}/update_releases.php 1 true

cd ${TEST_PATH}
DIFF=$(($CURRTIME-$LASTOPTIMIZE))
if [ "$DIFF" -gt 900 ] || [ "$DIFF" -lt 1 ]
then
	LASTOPTIMIZE=`date +%s`
	echo "Cleaning DB..."
	$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 1 true all yes
	$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 3 true other yes
	$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 5 true other yes
	$PHP ${TEST_PATH}/Releases/removeCrapReleases.php true 2
fi

cd ${NNTMUX_PATH}
DIFF=$(($CURRTIME-$LASTOPTIMIZE1))
if [ "$DIFF" -gt 43200 ] || [ "$DIFF" -lt 1 ]
then
	LASTOPTIMIZE1=`date +%s`
	echo "Optimizing DB..."
	$PHP ${NNTMUX_PATH}/optimise_db.php space
fi

echo "waiting ${NNTMUX_SLEEP_TIME} seconds..."
sleep ${NNTMUX_SLEEP_TIME}

done
