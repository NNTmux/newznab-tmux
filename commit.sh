#!/usr/bin/env bash

commit=`git log | grep "^commit" | wc -l`
commit=`expr $commit + 1`

sed -i -e "s/\$version=.*$/\$version=\"0.1r$commit\";/" bin/monitor.php
cp -f /etc/mysql/my.cnf conf/my.cnf
cp -f defaults.sh conf/defaults.sh
sed -i -e 's/export SVN_PASSWORD=.*$/export SVN_PASSWORD="password"/' conf/defaults.sh

git commit -a

