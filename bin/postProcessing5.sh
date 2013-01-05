#!/usr/bin/env bash
set -e

source ../edit_these.sh
eval $( sed -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" $NEWZPATH/www/config.php )

while :
do

  echo "Processing TV....." && $PHP processTv.php
  sleep 30

done
exit

