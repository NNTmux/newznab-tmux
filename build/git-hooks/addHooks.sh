#!/usr/bin/env bash

NNTMUX=`pwd`
HOOKS=/build/git-hooks
GIT=/.git/hooks
PC=/pre-commit
PM=/post-merge

NNTMUX=${NNTMUX%${HOOKS}}

echo "${NNTMUX}${GIT}"
if [ -x "${NNTMUX}${GIT}${PC}" ]
then
	rm "${NNTMUX}${GIT}${PC}"
	echo .
fi
if [ -x "${NNTMUX}${GIT}${PM}" ]
then
	rm "${NNTMUX}${GIT}${PM}"
	echo .
fi

ln -s ${NNTMUX}${HOOKS}${PC} ${NNTMUX}${GIT}${PC}
ln -s ${NNTMUX}${HOOKS}${PM} ${NNTMUX}${GIT}${PM}
