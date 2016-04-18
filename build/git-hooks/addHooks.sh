#!/usr/bin/env bash

NNTMUX=`pwd`
HOOKS=/build/git-hooks
GIT=/.git/hooks
PC=/pre-commit

NNTMUX=${NNTMUX%${HOOKS}}

echo "${NNTMUX}${GIT}"
if [ -x "${NNTMUX}${GIT}${PC}" ]
then
	rm "${NNTMUX}${GIT}${PC}"
	echo .
fi

ln -s ${NNTMUX}${HOOKS}${PC} ${NNTMUX}${GIT}${PC}
