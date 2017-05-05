#!/usr/bin/env bash

NNTMUX=`pwd`
HOOKS=/build/git-hooks
GIT=/.git/hooks
PC=/pre-commit
WSR=/white-space-removal

NNTMUX=${NNTMUX%${HOOKS}}

echo "${NNTMUX}${GIT}"
if [ -x "${NNTMUX}${GIT}${PC}" ]
then
	rm "${NNTMUX}${GIT}${PC}"
	echo .
fi
if [ -x "${NNTMUX}${GIT}${WSR}" ]
then
	rm "${NNTMUX}${GIT}${WSR}"
	echo .
fi

ln -s ${NNTMUX}${HOOKS}${PC} ${NNTMUX}${GIT}${PC}
ln -s ${NNTMUX}${HOOKS}${WSR} ${NNTMUX}${GIT}${WSR}
