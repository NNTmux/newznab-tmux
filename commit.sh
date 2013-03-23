#!/usr/bin/env bash

commit=`git log | grep "^commit" | wc -l`
commit=`expr $commit + 1`

sed -i -e "s/\$version=.*$/\$version=\"0.1r$commit\";/" bin/monitor.php

git commit -a

