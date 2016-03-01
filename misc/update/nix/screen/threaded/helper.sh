#!/bin/sh

if [ -e "NNBase.php" ]
then
	export NNTMUX_ROOT="$(pwd)"
else
	export NNTMUX_ROOT="$(php ../../../../../NNBase.php)"
fi

export NNTMUX_PATH="${NNTMUX_ROOT}/misc/update"
export TEST_PATH="${NNTMUX_ROOT}/misc/testing"
command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }
export NNTMUX_SLEEP_TIME="60"

while :
do

	cd ${NNTMUX_PATH}
	$PHP $NNTMUX_PATH/update_releases.php 1 false
	cd ${TEST_PATH}
	$PHP ${TEST_PATH}/Releases/removeCrapReleases.php true 1

	echo "waiting ${NNTMUX_SLEEP_TIME} seconds..."
	sleep ${NNTMUX_SLEEP_TIME}

done
