#!/usr/bin/env bash

commit=`git log | grep "^commit" | wc -l`
commit=`expr $commit + 1`

sed -i -e "s/\$version=.*$/\$version=\"0.3r$commit\";/" bin/monitor.php
cp -f /etc/mysql/my.cnf conf/jonnyboys_my.cnf
cp -f defaults.sh conf/jonnyboys_defaults.sh
sed -i -e 's/export SVN_PASSWORD=.*$/export SVN_PASSWORD="password"/' conf/jonnyboys_defaults.sh

git commit -a

