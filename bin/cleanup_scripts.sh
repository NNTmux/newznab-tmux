#!/usr/bin/env bash
set -e

source ../edit_these.sh

LASTOPTIMIZE1=`date +%s`
LASTOPTIMIZE2=`date +%s`
LASTOPTIMIZE3=`date +%s`
LASTOPTIMIZE4=`date +%s`
i=1
while [ $i -gt 0 ]

 do

#create releases from binaries
cd $NEWZNAB_PATH
[ -f update_releases.php ] && $PHP update_releases.php

CURRTIME=`date +%s`
#every 15 minutes and during first loop
DIFF=$(($CURRTIME-$LASTOPTIMIZE1))
if [ "$DIFF" -gt 900 ] || [ $i -eq 1 ]
then
        LASTOPTIMIZE1=`date +%s`
        cd $NEWZNAB_PATH
        [ -f update_predb.php ] && $PHP update_predb.php true
fi

CURRTIME=`date +%s`
#every 2 hours and during first loop
DIFF=$(($CURRTIME-$LASTOPTIMIZE2))
if [ "$DIFF" -gt 7200 ] || [ $i -eq 1 ]
then
        LASTOPTIMIZE2=`date +%s`
        cd $TESTING_PATH
        [ -f update_parsing.php ] && $PHP update_parsing.php
        [ -f removespecial.php ] && $PHP removespecial.php
	if [[ $CLEANUP == "true" ]]; then
	        [ -f update_cleanup.php ] && $PHP update_cleanup.php
	fi
fi

CURRTIME=`date +%s`
#every 12 hours
DIFF=$(($CURRTIME-$LASTOPTIMIZE3))
if [ "$DIFF" -gt 43200 ]
then
        LASTOPTIMIZE3=`date +%s`
	cd $INNODB_PATH
	if [[ $INNODB == "true" ]]; then
	        [ -f optimise_innodb.php ] && $PHP optimise_innodb.php
	else
		[ -f optimise_myisam.php ] && $PHP optimise_myisam.php
	fi
fi

CURRTIME=`date +%s`
#every 12 hours and during 1st loop
DIFF=$(($CURRTIME-$LASTOPTIMIZE4))
if [ "$DIFF" -gt 43200 ] || [ $i -eq 1 ]
then
        LASTOPTIMIZE4=`date +%s`
        cd $NEWZNAB_PATH
        [ -f update_tvschedule.php ] && $PHP update_tvschedule.php
        [ -f update_theaters.php ] && $PHP update_theaters.php
fi

i=`expr $i + 1`
echo "waiting $NEWZNAB_POST_SLEEP_TIME seconds..."
sleep $NEWZNAB_POST_SLEEP_TIME

done
