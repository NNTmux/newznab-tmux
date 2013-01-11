#!/usr/bin/env bash
set -e

source ../edit_these.sh
eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

while :
do

  $PHP processAlternate3.php

done

